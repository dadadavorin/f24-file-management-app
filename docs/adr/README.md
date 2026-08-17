# Architecture Decision Records

Five decisions, each with the alternatives that were considered and rejected.

| # | Decision | Why it mattered |
|---|---|---|
| [0001](0001-tree-storage.md) | Adjacency list plus a materialized path | Makes subtree delete and subtree search one indexed statement instead of a recursive walk |
| [0002](0002-postgres-collation-and-indexes.md) | PostgreSQL, `COLLATE "C"`, five indexes | Makes prefix search a bounded index scan with no sort — the query the whole design exists to serve |
| [0003](0003-pragmatic-layering.md) | Four layers, not full hexagonal | Enough structure to be readable, not so much that a six-endpoint API needs sixty files |
| [0004](0004-api-and-validation-conventions.md) | Value-object validation, RFC 9457 errors, generated contract | One definition of every rule, and a frontend that cannot drift from the API |
| [0005](0005-uniqueness-under-concurrency.md) | The database arbitrates uniqueness | Check-then-insert is a race; the unique index is the only thing that actually holds |

Decisions **not** given an ADR, because they were conventional rather than
contested: Laravel as the framework, React + Vite on the frontend, Docker
Compose for local development, and Railway for the demo deployment. Each is
explained in the README where it is relevant to running the project.

ADR-0002 supersedes an earlier draft of the index design that used
`text_pattern_ops`. The superseded reasoning and the reason it was wrong are
kept in that document deliberately — the mistake is more instructive than the
correction.
