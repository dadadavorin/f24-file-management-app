# TODOS

Deliberately deferred work. Each item was considered during design and
consciously left out of scope. Nothing here is an oversight — the reasoning is
recorded so the decision can be judged.

---

## 1. Frontend list virtualization

**What:** render only the visible rows of a folder listing (TanStack Virtual)
instead of every row fetched so far.

**Why:** infinite scroll appends DOM nodes without bound. The backend paginates
correctly with keyset cursors and stays fast, but scroll through 20,000 files in
one folder and the browser is holding 20,000 rows in the document, and scrolling
begins to stutter. The system degrades on the client, not the server.

**Pros:** the listing scales in the browser as well as in the database, closing
the scale story completely. TanStack Virtual is a small, well-trodden addition
to a list component that already exists.

**Cons:** it complicates the busiest component in the UI to solve a scenario
the demo dataset never reaches.

**Context:** purely a rendering concern — the API contract does not change, so
this can be added later without touching the backend at all. The natural place
is inside `FolderView`, wrapping the existing infinite query.

**Depends on:** the folder browsing UI being in place.

---

## 2. Large-scale seed data and a benchmark harness

**What:** a chunked bulk seeder capable of roughly one million nodes at
realistic depth, plus an artisan benchmark command emitting p50/p95 timings and
`EXPLAIN ANALYZE` output for each hot query, with the results tabulated in the
README.

**Why:** it would convert every scale claim in `docs/ARCHITECTURE.md` from an
assertion into a reproducible measurement anyone can run with one command.

**Pros:** replaces adjectives with numbers, and doubles as a regression guard —
it would catch the day a change turns an index scan into a sequential one.

**Cons:** the seeder must use chunked bulk inserts or seeding a million rows is
itself unusably slow; the benchmark table becomes a thing to maintain.

**Context:** considered during design and descoped in favour of a small demo
seeder. That decision stands. Two things soften it:

1. The seeder is being built with a `--count` parameter, so pointing it at a
   large number is already possible — the work is a benchmark command and a
   README section, not a seeder rewrite.
2. The silent-regression risk the benchmark would have caught is already
   covered by the `EXPLAIN` assertion test, which fails CI if the query plan
   degrades to a sequential scan or grows a sort step.

**Depends on:** nothing.

---

## 3. Move and rename operations

**What:** move a folder (or file) to a different parent, and rename a node.

**Why:** it is the most conspicuous missing capability in anything resembling a
file browser. It is also the operation that makes this specific tree design
expensive — which is precisely why it is worth writing down.

**Context — this is the interesting one.** [ADR-0001](docs/adr/0001-tree-storage.md)
chooses a materialized path *because* this system has no move operation: a path
is written once at insert and never rewritten, which removes the single largest
objection to the pattern. Adding move reintroduces that cost. The implementation
would be:

```
Moving node N (currently path = OLD, so its subtree matches OLD || N.id || '/')
under a new parent P (path = NEWBASE):

  1. Reject if P is N itself, or if P is inside N's subtree.
     Cycle check:  P.path LIKE (N.path || N.id || '/%')  OR  P.id = N.id
     Without this, a folder can be moved inside itself and the subtree is
     orphaned from the root with no error.

  2. Reject if the move would push any descendant past MAX_DEPTH:
        N.subtreeMaxDepth - N.depth + (P.depth + 1)  >  32

  3. Reject on name collision in the destination:
        the unique index on (parent_id, lower(name)) handles this — catch
        SQLSTATE 23505 exactly as CreateNode does. Do not check first.

  4. Rewrite the subtree, one statement:
        UPDATE nodes
           SET path  = (NEWBASE || P.id || '/') || substr(path, length(OLD || N.id || '/') + 1),
               depth = depth + (P.depth + 1 - N.depth)
         WHERE path LIKE (OLD || N.id || '/%');

  5. Repoint the node itself:
        UPDATE nodes SET parent_id = P.id, path = NEWBASE || P.id || '/',
               depth = P.depth + 1
         WHERE id = N.id;

  All of the above in ONE transaction. Steps 4 and 5 must not be observable
  separately, or the invariant between path and parent_id breaks mid-flight.
```

Rename is the cheap half: it touches `name` only, so no path rewrite is needed
at all — the path is built from ids, not names. That asymmetry is worth noting,
because it is a direct consequence of choosing id-based paths over name-based
ones.

**Pros:** records what the design costs, not only what it buys, so the next
person to want this feature starts from a plan rather than a surprise.

**Cons:** the sketch above has to actually be correct, since writing it down
invites it to be checked.

**Depends on:** nothing.

---

## Considered and rejected outright

Not TODOs — these are excluded permanently.

| Item | Why not |
|---|---|
| Authentication / authorization | Out of scope — this is a single-user system |
| File contents, uploads, MIME types | "A file is simply its name" |
| Substring or fuzzy search (trigram index) | Only prefix matching is required; a trigram index is a different cost profile |
| Soft delete / trash / restore | Not asked; adds a predicate to every query in the system |
| Real-time collaboration | Not asked |
| Mobile-responsive layout | Out of scope — desktop only, though the layout is not deliberately broken on narrow screens |
| Redis or any caching layer | Every hot query is one indexed lookup returning ≤100 rows. Infrastructure solving a problem that does not exist. |
