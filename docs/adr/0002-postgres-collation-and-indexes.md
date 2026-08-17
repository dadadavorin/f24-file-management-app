# ADR-0002: PostgreSQL, `COLLATE "C"`, and the five indexes

**Status:** Accepted — 2026-08-17
**Supersedes:** an earlier draft that used `text_pattern_ops`. See "What changed
and why" below — the earlier version was wrong in a way worth recording.

## Context

The typeahead query — the top 10 files whose name starts with a string,
evaluated once per keystroke — decides both the database and the index design.
It needs two things at once from one index: a **range scan** for the prefix
match, and an **ordering** so that `LIMIT 10` stops after ten rows instead of
sorting every match first.

## Decision

PostgreSQL 18. Every text column involved in searching or ordering is indexed
and queried as **`COLLATE "C"`** with the default operator class.

```sql
CREATE INDEX nodes_file_name
    ON nodes ((lower(name) COLLATE "C")) WHERE type = 'file';
```

```sql
SELECT id, name, parent_id, path
  FROM nodes
 WHERE type = 'file'
   AND lower(name) COLLATE "C" LIKE :prefix || '%' ESCAPE '\'
 ORDER BY lower(name) COLLATE "C"
 LIMIT 10;
```

Under `C` collation, comparison is byte-by-byte, so the planner can rewrite
`LIKE 'abc%'` into the range `>= 'abc' AND < 'abd'` **and** satisfy the
`ORDER BY` from the index's own order. One index, one scan, no sort node.

## What changed and why

The first version of this design used `text_pattern_ops`, on the reasoning that
a non-`C` collation prevents a plain B-tree index from serving `LIKE 'abc%'`.
That reasoning is correct as far as it goes, and the fix is incomplete.

`text_pattern_ops` orders by the `~<~` operator family, **not** by `<`. So
`ORDER BY lower(name)` — which asks for the default ordering under the database
collation — cannot be satisfied by a `text_pattern_ops` index. The real plan
would have been:

```
Limit
  └─ Sort  (Sort Key: lower(name))
       └─ Index Scan using nodes_file_name
```

Every row matching the prefix gets materialized and sorted **before** `LIMIT 10`
applies. For a one-character query on a large table that is the entire prefix
range, per keystroke. The index would have looked used and the query would still
have been O(matches · log matches).

`COLLATE "C"` on the indexed expression fixes both halves with one mechanism,
and removes the need to explain an operator class at all.

**The honest cost:** ordering becomes byte order, not locale order. `Z` sorts
before `a`; accented characters sort after ASCII. For a typeahead over file
names this is an acceptable and conventional trade — but it is a trade, not a
free win, and the README says so.

*(PostgreSQL 17+ also offers a builtin `C.UTF-8` collation provider, which would
achieve the same at the database level. Not used here, because a managed host
may not permit choosing the provider at initdb time, and a per-index `COLLATE`
works everywhere.)*

## The five indexes

```sql
-- 1. Exactly one root row, ever.
CREATE UNIQUE INDEX nodes_single_root
    ON nodes ((parent_id IS NULL)) WHERE parent_id IS NULL;

-- 2. No two items share a name in a folder, case-insensitively, under
--    concurrency. This is also what makes (sort_rank, lower(name)) a unique
--    keyset cursor within a parent — no id tiebreaker needed.
CREATE UNIQUE INDEX nodes_unique_name_per_parent
    ON nodes (parent_id, lower(name));

-- 3. Folder listing in display order. sort_rank is a STORED generated column
--    (0 = folder, 1 = file). It exists because 'file' < 'folder'
--    alphabetically, so ORDER BY type would put files first — and because
--    a mixed-direction sort (type DESC, name ASC) cannot be served by one
--    btree scan and breaks row-value keyset comparison, which requires
--    uniform direction. With sort_rank everything is ASC.
CREATE INDEX nodes_children_listing
    ON nodes (parent_id, sort_rank, (lower(name) COLLATE "C"));

-- 4. Prefix and exact search over files, with a usable ORDER BY. Partial:
--    folders are never search results.
CREATE INDEX nodes_file_name
    ON nodes ((lower(name) COLLATE "C")) WHERE type = 'file';

-- 5. Subtree operations. NOT partial: deleting a folder must remove folders
--    as well as files, so a `WHERE type = 'file'` predicate would disqualify
--    this index for the delete and it would sequentially scan the table.
CREATE INDEX nodes_path ON nodes (path COLLATE "C");
```

**Subtree-scoped search deliberately has no dedicated composite index.** Two
independent range predicates (`path LIKE …` and `name LIKE …`) cannot both be
range-scanned by one B-tree, so a `(path, name)` composite would still sort. The
planner instead chooses between index 4 (scan in name order, filter by path,
stop at 10 — excellent when the prefix is selective) and index 5 (scan the
subtree, filter by name — better when the subtree is small). Letting the planner
choose from two good single-purpose indexes beats a composite that serves
neither case well.

## Why PostgreSQL

**MySQL 8** uses an ordinary index for prefix `LIKE` regardless of collation, so
none of the above would arise. Rejected: no partial indexes, so the files-only
search index is not expressible, and weaker free managed hosting.

**SQLite** would remove the database service from deployment entirely. Rejected:
single-writer locking makes the concurrency behaviour in ADR-0005 impossible to
demonstrate.

## Consequences

- The suggestion query is a bounded index scan with no sort node. **Verified by
  an automated test** that seeds enough rows for the planner to prefer
  an index, runs `ANALYZE`, and asserts the plan names `nodes_file_name` and
  contains no `Sort` and no `Seq Scan on nodes`.
- **Every query touching `name` must use `lower(name) COLLATE "C"`.** Dropping
  either the `lower()` or the `COLLATE` silently stops the expression index from
  matching. The same test catches it.
- Byte-order sorting is user-visible in the suggestion dropdown. Documented.
