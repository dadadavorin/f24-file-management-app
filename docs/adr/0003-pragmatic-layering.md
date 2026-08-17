# ADR-0003: Pragmatic layering, not full hexagonal

**Status:** Accepted — 2026-08-17

## Context

Two failure modes are available:
too little structure (everything in controllers, reads as junior) and too much
(a six-endpoint API with sixty files and classes whose only job is copying
fields between representations, which reads as an inability to judge
proportion). Proportion is itself the signal.

## Decision

Four layers, dependencies pointing inward:

```
Http  →  Application  →  Domain  ←  Infrastructure
```

- **Domain** — value objects, enums, typed exceptions, and the `NodeRepository`
  interface. No framework imports.
- **Application** — one class per use case. Depends on the interface only.
- **Infrastructure** — `EloquentNodeRepository` implements the interface.
  Eloquent models are persistence and hold no business rules.
- **Http** — thin controllers, form requests for shape, API resources for
  output, one renderer mapping domain exceptions to responses.

Bound once, in `FileSystemServiceProvider`.

## What we deliberately did not do

Framework-agnostic domain **entities** with hand-written mappers to and from
Eloquent rows. That is the textbook hexagonal arrangement and the right call in
a large system with several persistence concerns. Here it would roughly double
the file count and make every change touch four files instead of two — the
opposite of what we want.

A DDD purist will correctly observe that Eloquent models leak persistence
concerns into the edge of the domain. That trade is deliberate, and this
paragraph is the answer to it.

## An honest limit on what this proves

`NodeRepository` carries roughly seven methods — `listChildren`,
`suggestByPrefix`, `findByExactName`, `deleteSubtree`, and so on. That is close
to being the SQL surface with a PHP name rather than a domain port in the strict
sense, and three of the five Application classes (`ListChildren`,
`FindFilesByExactName`, `SuggestFilesByPrefix`) largely forward their arguments.

The layer still earns its place: it keeps every use case discoverable by name,
it puts the two classes that *do* hold rules (`CreateNode`, `DeleteNode`) in an
obvious home, and it makes the HTTP layer trivially thin. But the claim is
"consistent and readable structure", not "the abstraction is load-bearing
everywhere."

## The in-memory fake, and why it needs a contract test

Application tests run against an `InMemoryNodeRepository` rather than a
database. That fake must reimplement keyset pagination, folders-first ordering,
case-insensitive prefix matching with `LIKE`-metacharacter escape semantics,
subtree matching by path prefix, and duplicate detection — every one of which is
where the real behavior lives.

A fake that drifts from the real implementation produces a green Application
suite describing behavior the system does not have, which is worse than having
no unit tests at all.

**Therefore: one shared contract test suite defines the repository's behavior
and is executed against both implementations.** Divergence fails the build. The
fake is only legitimate because that suite exists.

## Consequences

- SOLID is demonstrable by pointing at files: DIP via the interface, SRP via one
  class per use case, OCP via swappable persistence, LSP via the contract suite
  running unchanged against both implementations.
- Application tests are fast and need no database.
- Roughly 25–30 backend source files — enough to show structure, few enough to
  read in twenty minutes.
- **Cost:** two repository implementations to keep honest, paid for by the
  contract suite.
