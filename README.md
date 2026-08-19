# Browser-Based File System

A browser-based file system: create folders and nested subfolders, create
files inside them, search files by exact name, get prefix-match suggestions
while typing, and delete folders and files.

## 1. What this is

A single-page app backed by a REST API. One tree, rooted at a single node
everything else descends from. A file is only a name — no content, no size,
no upload.

**Live demo:** https://f24-file-management-app-production.up.railway.app/

## 2. Quick start

```bash
git clone https://github.com/dadadavorin/f24-file-management-app.git
cd f24-file-management-app
cp .env.example .env
docker compose up -d
```

The app is at [http://localhost:8080](http://localhost:8080). First boot
installs PHP and npm dependencies, generates an `APP_KEY`, and runs
migrations plus a small seeder, so the tree is not empty on first load.

```bash
composer check      # inside the app container: Pint + PHPStan (max) + Pest
npm run check        # inside web/: tsc --noEmit + ESLint
npm run test          # inside web/: Vitest
npm run test:e2e      # inside web/: Playwright, against the running stack
```

## 3. Running in debug mode

Debug mode is the default of `docker compose up`, not an opt-in flag.
`api/.env.example` ships with `APP_DEBUG=true`, so Laravel renders full stack
traces instead of a generic 500 page, and the `app` container's image
(`docker/php/Dockerfile`) has Xdebug installed and enabled.

Xdebug runs in trigger mode rather than always-on, so requests stay fast until
a debugger is actually listening:

```
docker/php/xdebug.ini
  xdebug.mode=debug
  xdebug.start_with_request=trigger
  xdebug.client_host=host.docker.internal
  xdebug.client_port=9003
```

Point an IDE's PHP Debug listener at port `9003`, add the `XDEBUG_TRIGGER`
cookie or query parameter (most IDE browser extensions do this for you), and
step through a request without rebuilding the container.

## 4. Architecture

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

One origin, one deployable unit: nginx serves the built SPA and proxies
`/api/*` to php-fpm, so there is no CORS configuration and no separate
frontend/backend deploys to keep in sync.

The backend is layered `Http → Application → Domain ← Infrastructure`, with
dependencies pointing inward — `Domain` imports nothing from Laravel, and
`Application` depends only on a `NodeRepository` interface. The reasoning for
that boundary, and where it was deliberately kept thin, is in
[ADR-0003](docs/adr/0003-pragmatic-layering.md).

Full topology, request-flow diagrams, and the directory layout are in
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md). Every non-conventional
decision has its own record in [`docs/adr/`](docs/adr/README.md).

## 5. Data model

One table. The tree shape is carried by two columns that must agree:

```
nodes
├── id          BIGSERIAL PRIMARY KEY
├── parent_id   BIGINT NULL → nodes(id) ON DELETE CASCADE   ← source of truth
├── type        'folder' | 'file'
├── name        VARCHAR(255)
├── path        VARCHAR(1024)  ancestor id chain            ← denormalized index
├── depth       SMALLINT
├── sort_rank   SMALLINT, generated: 0 for a folder, 1 for a file
├── created_at  TIMESTAMPTZ
└── updated_at  TIMESTAMPTZ
```

`parent_id` is the source of truth. Alongside it, every row also stores a
**materialized path** — the slash-delimited chain of its ancestors' ids,
excluding its own:

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

  Subtree of node n   ≡   path LIKE (n.path || n.id || '/%')
  Ancestors of id=31  ≡   id IN (1, 7, 22)      — parsed from the string
```

That one expression is what makes both a subtree delete and a subtree-scoped
search a single indexed statement instead of a recursive walk in application
code. Deleting a folder with an arbitrarily large subtree is:

```sql
DELETE FROM nodes WHERE path COLLATE "C" LIKE '/1/7/%';
DELETE FROM nodes WHERE id = 7;
```

both in one transaction, both hitting an index — never `N` queries for `N`
descendants.

### Why materialized path, not a recursive CTE or a closure table

The standard objection to materialized paths is that moving or renaming a
folder means rewriting every descendant's path. **This system has no move
operation** — it isn't in scope, and its absence is exactly what makes the
design cheap here: a path is written once, at insert, from the parent's path
alone, and never touched again. No insert-then-update, no application-generated
ids. Rename is free regardless, since the path is built from ids rather than
names.

An adjacency list with recursive CTEs was the simplest alternative and adds no
invariant to maintain, but a subtree search has to materialize every
descendant id before it can filter by name — the exact query this design needs
to stay fast at scale. A closure table (one row per ancestor–descendant pair)
would be the right call if move were in scope, but here it buys flexibility
that's never used at the cost of a table an order of magnitude larger.
`PostgreSQL ltree` was the closest alternative — genuinely a better fit for
this exact problem — and was passed over only because it requires
`CREATE EXTENSION ltree`, which not every managed Postgres host permits, and
because a plain `varchar` path stays portable to any SQL database.

Full alternatives and consequences, including the invariant test that keeps
`path` and `parent_id` from silently diverging: [ADR-0001](docs/adr/0001-tree-storage.md).

### Indexes

Five, each carrying a distinct job — folder listing order, name uniqueness
under concurrency, prefix search, and subtree operations. `sort_rank` exists
because `'file'` sorts before `'folder'` alphabetically, so folders-first
listing needs a generated column rather than `ORDER BY type`. The full
rationale, including why the subtree-search index and the prefix-search index
stay separate rather than merged into one composite, is in
[ADR-0002](docs/adr/0002-postgres-collation-and-indexes.md).

## 6. Collation and sort order

Every query that touches `name` — search, listing order, uniqueness — uses
`lower(name) COLLATE "C"`, not the database's default collation:

```sql
CREATE INDEX nodes_file_name
    ON nodes ((lower(name) COLLATE "C")) WHERE type = 'file';

SELECT id, name, parent_id, path FROM nodes
 WHERE type = 'file'
   AND lower(name) COLLATE "C" LIKE :prefix || '%' ESCAPE '\'
 ORDER BY lower(name) COLLATE "C"
 LIMIT 10;
```

The typeahead query — the top 10 files starting with a string, evaluated once
per keystroke — needs a **range scan** for the prefix match and an
**ordering** so `LIMIT 10` stops after ten rows instead of sorting every
match first. Under `C` collation, string comparison is byte-by-byte, so the
planner can rewrite `LIKE 'abc%'` into a range and satisfy the `ORDER BY` from
the same index — one scan, no sort node, verified in CI by an `EXPLAIN`
assertion that fails the build if the plan ever grows a `Sort` or falls back
to a sequential scan.

**The honest cost:** ordering becomes byte order, not locale order. `Z` sorts
before `a`, and accented characters sort after plain ASCII. For a file-name
typeahead that's an acceptable, conventional trade — but it is a trade, and an
earlier version of this design (using `text_pattern_ops` instead of
`COLLATE "C"`) looked like it fixed the same problem while silently leaving
the `ORDER BY` unsatisfied by the index, adding a sort node back in front of
the `LIMIT`. That mistake, and why `COLLATE "C"` fixes both halves with one
mechanism, is written up in [ADR-0002](docs/adr/0002-postgres-collation-and-indexes.md).

## 7. Stack rationale

| Choice | Why |
|---|---|
| **Laravel 13** | Routing, validation, migrations, and an Eloquent-backed persistence adapter are exactly what Laravel is for, and PHP 8.4 gives typed properties, enums, and readonly classes that make the domain layer worth writing. Nothing here needed a framework-agnostic core, so a lighter PSR-7 stack would have meant rebuilding routing and validation glue for no payoff. |
| **PostgreSQL 18** | Partial indexes and expression indexes are what makes the prefix-search and single-root constraints possible at all — MySQL doesn't support either. See [ADR-0002](docs/adr/0002-postgres-collation-and-indexes.md) for the full comparison, including why SQLite was ruled out (single-writer locking makes the concurrency behavior in [ADR-0005](docs/adr/0005-uniqueness-under-concurrency.md) impossible to demonstrate). |
| **React + Vite + TypeScript** | A folder browser is a tree of interactive, independently-loading views — exactly React's shape — and Vite's dev server plus HMR keeps the feedback loop short. TypeScript end to end, with types generated from the OpenAPI contract, means the frontend cannot silently drift from what the API actually returns. |
| **Docker Compose (local)** | `docker compose up` on a clean clone is the whole setup story: nginx, php-fpm, Postgres, and the Vite dev server, wired together with no host-machine PHP or Node version to match. |
| **Railway (deployment)** | Builds the same multi-stage `Dockerfile` used to verify the production image locally, runs it as one service alongside a managed Postgres instance, and runs migrations as a pre-deploy step — so "works locally" and "works deployed" are the same artifact, not two configurations that can diverge. |

## 8. Frontend dependencies

| Package | Why it's here |
|---|---|
| **TanStack Query** | Owns all server state — caching, background refetch, and the query-key isolation that keeps a fast typeahead from ever showing an out-of-order result. There is no separate client-state library because the only durable client state (the current folder) lives in the URL. |
| **React Router** | The current folder is a route param, not component state, which is what makes a deep link to a nested folder survive a refresh. |
| **Tailwind v4** | Utility CSS with no component-level stylesheet to keep in sync as the tree of components grows; v4's CSS-first config removes the separate `tailwind.config.js` entirely. |
| **Radix (Dialog)** | Focus trapping and keyboard handling inside a modal are easy to get wrong and hard to notice you got wrong. Used for the create/delete dialogs only — everywhere else is plain markup. |

## 9. API reference

Base path `/api/v1`. The full contract — every request and response shape,
generated from the Laravel controllers and API resources rather than
hand-written — is served at [`/docs/api`](https://f24-file-management-app-production.up.railway.app/docs/api)
and committed as [`openapi.json`](openapi.json).

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/nodes/root` | The root node — the SPA's entry point |
| `GET` | `/nodes/{id}` | One node plus its ancestor breadcrumb chain |
| `GET` | `/nodes/{id}/children?cursor=&limit=` | Paginated listing, folders first then name |
| `POST` | `/nodes` | Create a folder or a file (`type` discriminates) |
| `DELETE` | `/nodes/{id}` | Delete a node and, for folders, its whole subtree |
| `GET` | `/search/files?name=&parent_id=&scope=&cursor=&limit=` | Exact-name search, paginated |
| `GET` | `/search/suggestions?q=&parent_id=&scope=` | Top-10 prefix suggestions |

`POST /nodes` is a single endpoint rather than separate `/folders` and
`/files` routes — creating a file and a folder differ by exactly one enum
value, so splitting the endpoint would duplicate validation, the duplicate
check, and path construction for no gain.

The frontend never hand-writes a type for any of this: `npm run api:types`
regenerates `web/src/api/generated/types.ts` from `openapi.json`, and CI fails
if the committed output would differ from a fresh run.

## 10. Error contract

Two response shapes, deliberately, each where it already belongs rather than
forced into one:

**Field validation** returns Laravel's native shape, because that's what the
framework emits and what any Laravel-aware client already expects:

```json
{ "message": "The given data was invalid.",
  "errors": { "name": ["A name cannot be blank."] } }
```

**Everything else** — a name collision, a missing parent, an operation on the
root — returns [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) Problem
Details as `application/problem+json`, carrying a machine-readable `code` so
the UI can say "A folder named X already exists here" instead of "Something
went wrong":

```json
{ "type": "https://example.com/problems/duplicate-node-name",
  "title": "Duplicate name", "status": 409,
  "detail": "A folder named \"Invoices\" already exists here.",
  "code": "DUPLICATE_NODE_NAME" }
```

Collapsing both into one shape would mean either bolting a `code` field onto
Laravel's native validation response (fighting the framework) or reimplementing
field-error formatting by hand (reinventing it) — using each shape where it's
already the conventional one avoids both. Every domain exception is mapped to
its shape in exactly one place, so a new rule can't leak out as an
undifferentiated 500. Full rationale, including why the value object rather
than the form request owns every name rule, is in
[ADR-0004](docs/adr/0004-api-and-validation-conventions.md).

## 11. Testing

| Suite | Run with | Covers |
|---|---|---|
| `tests/Unit/Domain` | `composer check` (inside `api/`) | Value objects, every validation branch |
| `tests/Unit/Application` | `composer check` | Use-case rules, against an in-memory fake |
| `tests/Feature` | `composer check` | Endpoints, error paths, query plans, invariants |
| `web/**/*.test.tsx` | `npm run test` (inside `web/`) | Components, hooks, error and empty states |
| `e2e/` | `npm run test:e2e` (inside `web/`, stack running) | Two full journeys against the real stack |

Three tests are never skipped or deleted, because each guards a failure mode
that is otherwise silent — no error, just plausible-looking wrong behavior:

- **The `EXPLAIN` assertion** (`tests/Feature/Search`) — the only guard against
  the prefix-search index regressing into a sequential scan or growing a sort
  step.
- **The path invariant test** (`tests/Feature/Persistence`) — the only guard
  against `path` silently diverging from `parent_id`, which would make subtree
  search return the wrong rows and subtree delete remove the wrong ones.
- **The repository contract suite** (`tests/Feature/Persistence`) — runs the
  same behavioral suite against both the Eloquent repository and the
  in-memory fake the application tests use, so the fake can't drift into
  describing behavior the system doesn't actually have.

## 12. Deployment

The live demo runs on [Railway](https://railway.app): one Docker service built
from the repository's `Dockerfile`, alongside a managed PostgreSQL instance.
`railway.json` runs `php artisan migrate --force` as a pre-deploy step, so a
deploy and its migrations are one release, not two steps that can fall out of
sync — and points Railway's health check at `/api/v1/health`.

The `Dockerfile` is a three-stage build: the frontend is built with Vite,
composer dependencies are installed without dev packages, and the final image
layers both onto `php:8.4-fpm-alpine` with nginx serving the SPA and proxying
`/api/*` to php-fpm on one port — the same single-origin shape described in
[§4](#4-architecture), just without the local dev server in front of it.

To build and run that production image locally, against a real Postgres
instance, before pushing it anywhere:

```bash
export APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
docker compose -f docker-compose.prod.yml up --build -d
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
curl -f http://localhost:8080/api/v1/health
```

Deploying elsewhere needs, at minimum: `APP_KEY`, `APP_URL`, and the five
`DB_*` variables pointed at a PostgreSQL 18 instance. Everything else in
`api/.env.example` has a workable default.

## 13. Resolved ambiguities

Three requirements that could reasonably be read two ways. Each is
implemented as the more permissive of the two readings, so the other one is a
single parameter away rather than a rewrite.

| Question | Decision | Reasoning |
|---|---|---|
| Does searching "within a parent folder" mean its direct children, or its whole subtree? | **Whole subtree** | Matches how a folder browser is generally expected to behave — searching inside a folder descends into it. A `scope` parameter (`subtree` or `children`) is exposed on both search endpoints, so the narrower reading is one query-string value away. |
| Can two items share a name inside the same folder? | **No** — enforced case-insensitively | Matches every mainstream file browser. Enforced by a database unique index rather than an application-level check, so it holds under concurrent requests — see [ADR-0005](docs/adr/0005-uniqueness-under-concurrency.md). |
| Is prefix and exact-name search case-sensitive? | **No** | Users typing into a search box do not expect case sensitivity. Applied consistently via `lower(name)` in both the index and every query that reads it. |

## 14. Out of scope

Authentication, file content and uploads, and move/rename are not
implemented: this is a single-user system where a file is only a name, and
move/rename were never part of the design goal. What's more interesting than
the exclusion itself is what it costs to add back, particularly move: the
tree design in [§5](#5-data-model) is cheap specifically *because* no path is
ever rewritten after insert, and adding move reintroduces that cost. That
tradeoff, along with frontend list virtualization and a large-scale seed
benchmark, is written up with the reasoning and a concrete implementation
sketch in [`TODOS.md`](TODOS.md) — each was considered during design and
deliberately left out, not overlooked.
