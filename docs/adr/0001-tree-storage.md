# ADR-0001: Adjacency list plus a materialized path

**Status:** Accepted — 2026-08-17
**This is the load-bearing decision of the project.**

## Context

The system must scale to a large number of nodes, and must support deleting a
folder (and therefore its entire subtree, at arbitrary depth) and searching for
files inside a folder. Both are subtree operations. How the tree is stored decides
whether they are one indexed statement or a recursive walk.

## Decision

`parent_id` is the source of truth, with `ON DELETE CASCADE` on a
self-referencing foreign key. Alongside it, each row stores a **materialized
path**: the slash-delimited chain of its ancestors' ids, excluding its own.

```
        id=1  path='/'         depth=0     ← the one real root row
          │
          ├── id=7   path='/1/'       depth=1     "Documents"
          │     ├── id=22  path='/1/7/'    depth=2     "Invoices"
          │     │     └── id=31  path='/1/7/22/' depth=3     "march.pdf"
          │     └── id=23  path='/1/7/'    depth=2     "notes.txt"
          └── id=8   path='/1/'       depth=1     "Photos"

  Subtree of node n   ≡   path LIKE (n.path || n.id || '/%')
  Ancestors of node n ≡   id IN (ids parsed from n.path)
```

Because the path excludes the node's own id, it is computable from the parent
alone — so a row is written in a **single insert** with an ordinary
auto-increment primary key. No insert-then-update, no application-generated ids.

Both columns are written in one place, a single repository method, and their
agreement is guarded by a dedicated invariant test.

## Why this is cheap *here specifically*

The standard objection to materialized paths is that moving or renaming a folder
requires rewriting every descendant's path.

**This system has no move operation.** A path is written once, at insert, and
never touched again. The absence of a feature is what makes the design cheap —
a reason that applies here and would not apply in a general-purpose file system.

(Rename is free regardless: the path is built from ids, not names, so renaming
touches only the `name` column. That asymmetry is a direct consequence of
choosing id-based paths. The migration path for move, should it ever be needed,
is written out in `TODOS.md` §3.)

## Alternatives considered

**Adjacency list only, with recursive CTEs.** Simplest possible schema, no
invariant to maintain. Rejected because subtree search must first materialize
every descendant id and then filter by name — the query that collapses at the
scale this system targets.

**PostgreSQL `ltree`.** The database's own purpose-built type for exactly this
problem: `path <@ '1.7'` for containment, GiST-indexed, with no `LIKE` and no
escaping question at all. Genuinely the strongest alternative, and a closer fit
than the hand-rolled string. Rejected for two reasons: it requires
`CREATE EXTENSION ltree`, which is not guaranteed on every managed PostgreSQL
host and would make the deployment story conditional; and a `varchar` path is
portable to any SQL database, whereas `ltree` binds the schema to PostgreSQL
permanently. **This is a close call, and on a project already committed to
PostgreSQL it could reasonably go the other way.**

**Closure table.** Most flexible, and the right answer if moves were in scope.
Rejected: one row per ancestor-descendant pair means a table an order of
magnitude larger, and writes touching many rows, to buy flexibility never used.

**Nested sets.** Rejected. Excellent reads; a single insert can renumber a large
fraction of the table.

## Consequences

- Deleting a folder is one set-based statement regardless of subtree size —
  **provided a non-partial index exists on `path`** (see ADR-0002; an earlier
  draft of this design had only a files-only partial index and the delete would
  have sequentially scanned the whole table).
- Breadcrumbs come from parsing the path in PHP plus one `whereIn` — no
  per-ancestor query.
- Search results can display their containing folder without an N+1: collect
  every ancestor id across all result paths, one `whereIn`, build labels in PHP.
- **Cost:** two representations of one relationship must agree. If they diverge,
  subtree search returns wrong results and subtree delete removes the wrong rows
  — silently, with no error. Mitigated by writing both in a single repository
  method, by the database cascade as an independent net, and by an invariant
  test that rebuilds every node's ancestor chain and asserts it matches.
- `path` is `VARCHAR(1024)` and depth is capped at 32 in the domain, so overflow
  surfaces as a clean 422 rather than a database error.
