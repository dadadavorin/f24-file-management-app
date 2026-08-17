# Architecture

Decisions and their rejected alternatives live in [`docs/adr/`](adr/README.md).

---

## 1. Stack

| Layer | Choice | ADR |
|---|---|---|
| Backend | PHP 8.4, Laravel 13 | *conventional — see README* |
| Database | PostgreSQL 18 | [0002](adr/0002-postgres-collation-and-indexes.md) |
| Tree storage | Adjacency list + materialized path | [0001](adr/0001-tree-storage.md) |
| Layering | Http → Application → Domain ← Infrastructure | [0003](adr/0003-pragmatic-layering.md) |
| Validation, errors, contract | Value objects, RFC 9457, generated OpenAPI | [0004](adr/0004-api-and-validation-conventions.md) |
| Uniqueness | Database unique index, no check-then-insert | [0005](adr/0005-uniqueness-under-concurrency.md) |
| Frontend | Vite + React 19 + TS, TanStack Query, Tailwind v4, Radix | *conventional — see README* |
| Local dev | Docker Compose: nginx + php-fpm + postgres + vite | *conventional — see README* |
| Deployment | Railway, single origin | *conventional — see README* |
| Tests | Pest 4, Vitest + RTL, Playwright | — |

## 2. System topology

```
┌──────────────────────┐               ┌─────────────────────────────────────┐
│  Browser             │               │  Single origin (Railway)            │
│                      │               │                                     │
│  React SPA           │  GET /        │  ┌───────────────────────────────┐  │
│  - TanStack Query    │──────────────>│  │ nginx                         │  │
│  - React Router      │               │  │  /api/*  → php-fpm            │  │
│  - Tailwind          │  /api/v1/*    │  │  /*      → SPA index.html     │  │
│                      │<─────────────>│  └───────────┬───────────────────┘  │
└──────────────────────┘   JSON        │              │                      │
                                       │       ┌──────▼───────┐              │
   No CORS. No split env.              │       │  Laravel 13  │              │
   Deep links survive refresh          │       └──────┬───────┘              │
   because nginx falls back            │              │ SQL                  │
   to index.html.                      │       ┌──────▼───────┐              │
                                       │       │ PostgreSQL 18│              │
                                       │       └──────────────┘              │
                                       └─────────────────────────────────────┘
```

## 3. Backend layering

Dependencies point **inward**. `Application` never imports Eloquent; the binding
happens once, in a service provider.

```
  HTTP                       Application                Domain                    Infrastructure
  ──────────────────────     ────────────────────       ─────────────────────     ──────────────────────
  routes/api.php             CreateNode                 NodeName        (VO)      EloquentNodeRepository
      │                      DeleteNode                 NodePath        (VO)          │
  FormRequest                ListChildren               NodeType        (enum)        │
   (shape only) ──DTO──>     FindFilesByExactName  ──>  NodeRepository  (iface)  ──> Node (Eloquent)
      │                      SuggestFilesByPrefix       DomainException tree          │
  Controller                                                                          ▼
      │                              ▲                          ▲              PostgreSQL
  ApiResource                        │                          │
      │                              └──── depends only on ─────┘
  JSON / problem+json                       the interface
      ▲
      │
  DomainExceptionRenderer  ← single place mapping every domain exception to HTTP
```

### Directory layout

```
app/
├── Domain/FileSystem/
│   ├── Enum/NodeType.php                     folder | file
│   ├── ValueObject/NodeName.php              ← THE single source of name rules
│   ├── ValueObject/NodePath.php              materialized-path arithmetic
│   ├── Repository/NodeRepository.php         interface (the port)
│   ├── Dto/{NodeData,NodePage,Cursor}.php
│   └── Exception/
│       ├── NodeNotFound.php                  → 404
│       ├── DuplicateNodeName.php             → 409
│       ├── ParentIsNotAFolder.php            → 422
│       ├── RootIsImmutable.php               → 422
│       ├── MaxDepthExceeded.php              → 422
│       └── InvalidNodeName.php               → 422 (carries its own field name)
├── Application/FileSystem/
│   ├── CreateNode.php                        one action, type is a parameter (DRY)
│   ├── DeleteNode.php
│   ├── ListChildren.php
│   ├── FindFilesByExactName.php
│   └── SuggestFilesByPrefix.php
├── Infrastructure/Persistence/Eloquent/
│   ├── Models/Node.php                       persistence only, no business rules
│   └── EloquentNodeRepository.php            the adapter
├── Http/
│   ├── Controllers/Api/V1/{NodeController,SearchController}.php
│   ├── Requests/{CreateNodeRequest,ListChildrenRequest,SearchRequest}.php
│   ├── Resources/NodeResource.php
│   └── Rendering/DomainExceptionRenderer.php
└── Providers/FileSystemServiceProvider.php   binds NodeRepository → Eloquent impl
```

## 4. Data model

One table. The tree shape is carried by two columns that must agree.

```
nodes
├── id          BIGSERIAL PRIMARY KEY
├── parent_id   BIGINT NULL → nodes(id) ON DELETE CASCADE   ← SOURCE OF TRUTH
├── type        VARCHAR  'folder' | 'file'
├── sort_rank   SMALLINT GENERATED ALWAYS AS                ← 0 = folder, 1 = file
│                 (CASE WHEN type='folder' THEN 0 ELSE 1 END) STORED
├── name        VARCHAR(255)
├── path        VARCHAR(1024)  ancestor id chain            ← DENORMALIZED INDEX
├── depth       SMALLINT
├── created_at  TIMESTAMPTZ
└── updated_at  TIMESTAMPTZ
```

### Why `sort_rank` exists

Folders display before files. `type` cannot produce that ordering: `'file'`
sorts before `'folder'` alphabetically, so `ORDER BY type` puts files first. The
obvious repair, `ORDER BY type DESC, lower(name) ASC`, is **mixed-direction** —
which one B-tree scan cannot serve, and which invalidates row-value comparison
in PostgreSQL, breaking the keyset cursor. A stored generated `sort_rank` makes
every sort key ascending, so one index serves the listing and the cursor is a
plain row comparison.

### How `path` works

```
        id=1  path='/'        depth=0    ← the one real root row
          │
          ├── id=7   path='/1/'      depth=1     "Documents"
          │     │
          │     ├── id=22  path='/1/7/'   depth=2     "Invoices"
          │     │     └── id=31  path='/1/7/22/'  depth=3   "march.pdf"
          │     │
          │     └── id=23  path='/1/7/'   depth=2     "notes.txt"
          │
          └── id=8   path='/1/'      depth=1     "Photos"

  path == the ids of ALL ANCESTORS, in order, slash-delimited, excluding self.

  Subtree of node n   ≡   path LIKE (n.path || n.id || '/%')
  Subtree of id=7     ≡   path LIKE '/1/7/%'          → 22, 23, 31
  Ancestors of id=31  ≡   id IN (1, 7, 22)            → parsed from the string
  Depth of node n     ≡   n.depth                     (stored, not computed)
```

Because `path` never contains the node's own id, it is written in a **single
insert** with an auto-increment primary key. **There is no move operation**,
so a path is written once and never rewritten — see
[ADR-0001](adr/0001-tree-storage.md).

**The root's id is not assumed to be 1.** It is a `BIGSERIAL` value from a seed
migration; `GET /api/v1/nodes/root` returns it, and nothing hardcodes it.

### Indexes

Five, each justified in [ADR-0002](adr/0002-postgres-collation-and-indexes.md).

```sql
-- 1. Exactly one root row, ever.
CREATE UNIQUE INDEX nodes_single_root
    ON nodes ((parent_id IS NULL)) WHERE parent_id IS NULL;

-- 2. Case-insensitive name uniqueness within a folder, enforced under
--    concurrency. Also makes (sort_rank, lower(name)) a unique keyset cursor,
--    so no id tiebreaker is needed.
CREATE UNIQUE INDEX nodes_unique_name_per_parent
    ON nodes (parent_id, lower(name));

-- 3. Folder listing in display order. All ascending — see sort_rank above.
CREATE INDEX nodes_children_listing
    ON nodes (parent_id, sort_rank, (lower(name) COLLATE "C"));

-- 4. Prefix and exact search over files.
--    COLLATE "C" gives BOTH the LIKE range scan AND a usable ORDER BY pathkey
--    from one index, so LIMIT 10 stops after ten rows instead of sorting every
--    match. (text_pattern_ops would give the range scan but NOT the ordering.)
CREATE INDEX nodes_file_name
    ON nodes ((lower(name) COLLATE "C")) WHERE type = 'file';

-- 5. Subtree operations. NOT partial — deleting a folder removes folders too,
--    so a `WHERE type='file'` predicate would disqualify this index for the
--    DELETE and it would sequentially scan the entire table.
CREATE INDEX nodes_path ON nodes (path COLLATE "C");
```

**Subtree-scoped search has no dedicated composite index, deliberately.** Two
independent range predicates cannot both be range-scanned by one B-tree, so a
`(path, name)` composite would still sort. The planner chooses between index 4
(scan in name order, filter by path, stop at 10) and index 5 (scan the subtree,
filter by name) based on statistics.

## 5. Request flows

### Prefix suggestion (the hot path — one request per keystroke)

```
  User types "inv"
        │
        ▼
  SearchBox  ──debounce 250ms──> useSuggestions(q, scope)
        │                              │
        │                        TanStack Query
        │                        - queryKey (q, scope): each keystroke is its own
        │                          cache entry, so an older response can never
        │                          overwrite a newer one. THIS is what prevents
        │                          out-of-order results.
        │                        - placeholderData: keepPreviousData, so the
        │                          dropdown does not flicker empty between keys
        ▼                              │
  GET /api/v1/search/suggestions?q=inv&parent_id=7&scope=subtree
        │
        ▼
  SearchRequest        q is a string, ≤255 chars
        │
        ▼
  SuggestFilesByPrefix
        │
        ├── q is blank after trim?  ──yes──> return []   ← never touches the DB
        │
        ├── ESCAPE LIKE METACHARACTERS:  \ → \\ ,  % → \% ,  _ → \_
        │   (unescaped, a user typing "%" matches the entire table)
        │
        ▼
  NodeRepository::suggestByPrefix()
        │
        ▼
  SELECT id, name, parent_id, path
    FROM nodes
   WHERE type = 'file'
     AND lower(name) COLLATE "C" LIKE :prefix || '%' ESCAPE '\'
     [AND path COLLATE "C" LIKE :subtree]          -- only when scoped
   ORDER BY lower(name) COLLATE "C"
   LIMIT 10;
        │
        ▼  Limit → Index Scan using nodes_file_name        ← NO Sort node.
        │                                                    Asserted by the
        │                                                    EXPLAIN test.
  ≤10 rows → NodeResource[] → JSON
```

### Delete a folder

```
  DELETE /api/v1/nodes/7
        │
        ▼
  DeleteNode
        ├── node not found        ──> NodeNotFound      404
        ├── node is the root      ──> RootIsImmutable   422
        │
        ▼  BEGIN TRANSACTION
        │
        │  DELETE FROM nodes WHERE path COLLATE "C" LIKE '/1/7/%';
        │      └─ Index Scan using nodes_path  ← the non-partial index; with
        │         only a files-only index here this would seq-scan the table
        │  DELETE FROM nodes WHERE id = 7;
        │
        ▼  COMMIT
        │
       204 No Content

  The self-referencing FK ON DELETE CASCADE stays in place as a correctness net.
```

### Create a node — note what is NOT here

```
  POST /api/v1/nodes  { parent_id, type, name }
        │
        ▼
  CreateNodeRequest        shape only: parent_id integer, type in enum, name string
        │
        ▼
  NodeName::from($name)    ← ALL name rules live here and nowhere else
        │                    trim, non-blank, ≤255, no '/', no control characters
        │                    fails → InvalidNodeName (carrying field 'name') → 422
        ▼
  CreateNode
        ├── parent missing       ──> NodeNotFound        404
        ├── parent is a file     ──> ParentIsNotAFolder  422
        ├── parent.depth+1 > 32  ──> MaxDepthExceeded    422
        │
        │   ⚠ There is deliberately NO "does this name already exist?" SELECT.
        │     Check-then-insert is a race: two concurrent requests both pass
        │     the check and both insert. The unique index is the arbiter.
        ▼
  INSERT INTO nodes (parent_id, type, name, path, depth)
        │
        ├── SQLSTATE 23505  ──> DuplicateNodeName ──> 409 Conflict
        │
        ▼
       201 Created + NodeResource
```

## 6. API contract

Base path `/api/v1`. OpenAPI 3.1 generated from the code, served at `/docs/api`.

| Method | Path | Purpose | Success |
|---|---|---|---|
| `GET` | `/nodes/root` | The root node — the SPA's entry point | 200 |
| `GET` | `/nodes/{id}` | One node plus its ancestor breadcrumb chain | 200 |
| `GET` | `/nodes/{id}/children?cursor=&limit=` | Paginated listing, folders first then name, with capped child counts | 200 |
| `POST` | `/nodes` | Create a folder or a file (`type` discriminates) | 201 |
| `DELETE` | `/nodes/{id}` | Delete a node and, for folders, its whole subtree | 204 |
| `GET` | `/search/files?name=&parent_id=&scope=&cursor=&limit=` | Exact-name search, **paginated** (FR-3, FR-4) | 200 |
| `GET` | `/search/suggestions?q=&parent_id=&scope=` | Top-10 prefix suggestions (FR-5) | 200 |

Seven endpoints. `POST /nodes` is a **single** endpoint rather than `/folders`
and `/files` — creating a file and a folder differ by exactly one enum value, so
two endpoints would duplicate validation, the duplicate check, the depth check,
and path construction.

**Exact-name search is paginated** for the same reason listings are: identically
named files may exist anywhere in the tree, so the result set is unbounded by
nature. Each result also carries its containing folder label — resolved without
an N+1 by collecting every ancestor id across all result paths and issuing one
`whereIn`.

**Child counts are capped.** `count(*)` per folder over a page of folders is the
most expensive query on the listing, and no requirement asks for it. Each count
stops at 100 and renders as `99+` beyond that, so a folder holding a large
subtree costs the same as a small one.

### Error contract

Two shapes, deliberately — full rationale in
[ADR-0004](adr/0004-api-and-validation-conventions.md).

Field validation returns Laravel's native 422 (`{message, errors}`). Everything
else returns RFC 9457 Problem Details as `application/problem+json`, carrying a
machine-readable `code` so the UI can say "A folder named X already exists here"
rather than "Something went wrong". Every domain exception is mapped in one
place, so a new rule cannot leak as a 500.

## 7. Frontend architecture

```
src/
├── api/
│   ├── generated/types.ts       ← GENERATED from OpenAPI. Never hand-edited.
│   └── client.ts                thin typed fetch wrapper, problem+json aware
├── features/filesystem/
│   ├── components/  FolderView, NodeRow, Breadcrumbs, SearchBox,
│   │                CreateNodeDialog, DeleteConfirmDialog, EmptyState
│   ├── hooks/       useRoot, useChildren, useNode, useCreateNode,
│   │                useDeleteNode, useSuggestions, useExactSearch
│   └── routes.tsx
├── components/ui/   Button, Dialog, Input, Spinner, Skeleton  (Radix + Tailwind)
└── lib/             cn, useDebouncedValue, formatError
```

- **Server state lives in TanStack Query. There is no client state library** —
  the only durable client state is the current folder id, and that lives in the
  URL (`/folders/:id`), which is what makes deep links and the back button work.
  The entry route resolves the root through `GET /nodes/root`; no id is
  hardcoded.
- **Stale typeahead results are prevented by query-key isolation**, not by
  request cancellation. TanStack Query aborts on `cancelQueries` or garbage
  collection, *not* when a key changes — earlier in-flight requests run to
  completion and land in their own cache entries, where nothing reads them.
- **Mutations invalidate and refetch; they are not optimistic.** Placing a
  created node correctly into a keyset-paginated, folders-first list requires a
  server-assigned id on a page that may not be loaded — and the commonest
  failure is a 409 duplicate name, so an optimistic row would appear and vanish
  exactly when the user needs clarity.
- **No component calls `fetch` directly.** Every request goes through a hook,
  every hook through the typed client.
- Radix is used for `Dialog` and `DropdownMenu` only, because focus trapping and
  keyboard handling are genuinely hard to get right and trivially wrong.

## 8. Code conventions

Enforced by CI. These are the load-bearing rules of the codebase; if one looks
wrong while implementing, raise it rather than quietly doing something else.

1. **`app/Domain/` imports nothing from Laravel.** No facades, no `Illuminate`,
   no helpers. If domain code needs a framework feature, the design is wrong.
2. **`app/Application/` depends on the `NodeRepository` interface only** — never
   on `EloquentNodeRepository` and never on an Eloquent model.
3. **Name rules live in `NodeName` and nowhere else.** Form requests declare
   shape and nothing more.
4. **Never check-then-insert for duplicate names.** The unique index arbitrates.
5. **Always escape `\`, `%`, `_` in `LIKE` input** with an explicit `ESCAPE`
   clause. An unescaped `%` matches the entire table.
6. **Every query on `name` uses `lower(name) COLLATE "C"`.** Dropping either the
   `lower()` or the `COLLATE` silently stops the expression index matching.
7. **Never walk the tree in PHP.** Subtree operations use `path`; breadcrumbs
   parse `path` and issue one `whereIn`. One query per ancestor is a bug.
8. **Never `OFFSET`.** Listings and search results use keyset pagination.
9. **Never one query per row.** Child counts come from a single capped grouped
   aggregate per page.
10. **`web/src/api/generated/` is generated.** Never hand-edited.
11. **No component calls `fetch`.** Hooks, then the typed client.

**PHP:** `declare(strict_types=1)` everywhere. `final readonly class` for DTOs
and value objects. Constructor property promotion. Backed enums. PHPStan via
Larastan at max level, scoped to `app/` (the level is meaningful over
first-party code; running it across framework glue produces noise that would
have to be suppressed, which is a baseline by another name).

**TypeScript:** `strict: true`. No `any`. No non-null assertions.

### Comments

Comment sparingly. A comment earns its place by explaining something the code
cannot say for itself — a constraint, a footgun, or a decision that looks wrong
until you know why. Anything else is maintenance debt.

**Write a comment for:** a non-obvious invariant; a deliberate choice a reader
would otherwise "fix" (the absence of a duplicate-name check, the `COLLATE "C"`
on every name query); a class docblock where the class's job is not obvious from
its name and signature.

**Do not write:** comments restating the code; `@param`/`@return` blocks that
repeat native types; section banners; commented-out code; TODOs without an
owner and a reason.

Keep them to one or two lines. If explaining takes a paragraph, it belongs in
an ADR and the comment should link to it.

### Diagrams in docblocks

Three files carry an ASCII diagram, because in each case the shape is genuinely
hard to hold in your head from the code alone:

| File | Diagram |
|---|---|
| `Domain/ValueObject/NodePath.php` | The tree-to-path illustration from §4 — the whole scheme is unreadable without it |
| `database/migrations/*_create_nodes_table.php` | The five indexes, with the `COLLATE "C"` and non-partial-`path` warnings — this is where a future change breaks performance silently |
| `features/filesystem/hooks/useSuggestions.ts` | Why staleness is prevented by query-key isolation and not by cancellation — otherwise someone "optimizes" it back into a bug |

Nowhere else. **When behavior a diagram describes changes, update the diagram in
the same commit** — a stale diagram actively misleads.

## 9. Failure modes

| Codepath | Realistic failure | Test | Handled | User sees |
|---|---|---|---|---|
| Create node | Concurrent identical name | ✅ | Unique index → 409 | "A folder named X already exists here" |
| Create node | Parent deleted mid-request | ✅ | FK violation → 404 | "That folder no longer exists" |
| Create node | Nesting past depth 32 | ✅ | `MaxDepthExceeded` → 422 | "Folders can't be nested more than 32 deep" |
| Delete folder | Very large subtree | ✅ | One indexed set-based statement | Row disappears |
| Delete | Deleting the folder you're viewing | ✅ | 404 on next fetch | Navigates to the nearest surviving ancestor |
| Prefix search | Query contains `%`, `_`, `\` | ✅ | Escaped with `ESCAPE '\'` | Literal match only |
| Prefix search | Fast typing, out-of-order responses | ✅ | Query-key isolation | Only the latest results |
| Prefix search | Blank query | ✅ | Short-circuit, no query issued | Dropdown closed |
| **Prefix search** | **Plan degrades to a Sort or Seq Scan** | ✅ **EXPLAIN test** | — | *Nothing — this is why the test exists* |
| **Any subtree op** | **`path` diverges from `parent_id`** | ✅ **invariant test** | FK cascade net | *Nothing — this is why the test exists* |
| **Application layer** | **In-memory fake diverges from Eloquent** | ✅ **contract suite** | — | *Nothing — a green suite proving nothing* |
| List children | Enormous folder | ✅ | Keyset pagination | Infinite scroll |
| Search | Many identically-named files | ✅ | Paginated, folder labels resolved in one query | Scrollable results with locations |
| Any | Database unreachable | ✅ | 500 + problem+json | Error state with retry |

The three rows in bold are the only **silent** failure modes in this design —
they produce plausible-looking wrong behavior with no error at all. Each is
guarded by a dedicated test rather than by error handling, because there is
nothing to catch.
