# ADR-0005: The database arbitrates uniqueness, not the application

**Status:** Accepted — 2026-08-17

## Context

Two items must not share a name inside the same folder. The obvious
implementation is to check before inserting:

```php
if ($repo->existsIn($parentId, $name)) {
    throw new DuplicateNodeName();
}
$repo->insert(...);
```

This is wrong, and it is wrong in a way that passes every test written against
a single request. Two concurrent requests both run the check, both find nothing,
and both insert. The result is duplicate names in a folder and a constraint the
rest of the system incorrectly believes is guaranteed.

## Decision

There is **no pre-insert existence check**. A unique index arbitrates:

```sql
CREATE UNIQUE INDEX nodes_unique_name_per_parent
    ON nodes (parent_id, lower(name));
```

`EloquentNodeRepository` catches the resulting `QueryException`, inspects
SQLSTATE `23505`, and translates it into the domain's `DuplicateNodeName`, which
the renderer turns into a 409.

The SQLSTATE inspection lives in the repository — the one class allowed to know
about the database — so the domain and application layers see only the domain
exception. The in-memory fake must produce the same exception under the same
conditions, which the repository contract suite (ADR-0003) enforces.

This index is doing a second job: because `lower(name)` is unique within a
parent, `(sort_rank, lower(name))` is a unique ordering within a folder, which
is what makes the keyset pagination cursor work without an `id` tiebreaker.

## Related: exactly one root

The root is a real row with `parent_id IS NULL`.

The reason for a real root row is **not** that PostgreSQL cannot constrain
`NULL` — since PostgreSQL 15, `UNIQUE NULLS NOT DISTINCT` does exactly that, and
on PostgreSQL 18 it would close the hole directly. The reason is that a real
root removes the `if (parent === null)` branch from every service, every query,
every resource, and every frontend component. Uniformity in the code, not a
limitation in the database.

Exactly one root is guaranteed by a partial unique index:

```sql
CREATE UNIQUE INDEX nodes_single_root
    ON nodes ((parent_id IS NULL)) WHERE parent_id IS NULL;
```

Because the root's id is a `BIGSERIAL` value assigned by a seed migration, it is
**not** assumed to be `1` anywhere. `GET /api/v1/nodes/root` returns it, and the
frontend resolves the entry point through that endpoint rather than hardcoding
an id that would differ in any environment where migrations replayed.

## Consequences

- Uniqueness holds under concurrency, which is the only condition under which
  the claim means anything.
- Case-insensitivity comes from the same index that enforces uniqueness, so the
  two cannot disagree.
- **Required test:** an integration test triggering the unique violation
  directly and asserting a 409 with `code: DUPLICATE_NODE_NAME` — not a 500. An
  untranslated `QueryException` is the natural failure mode of this design.
