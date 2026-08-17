# ADR-0004: API conventions — validation, errors, and the generated contract

**Status:** Accepted — 2026-08-17
**Merges** three earlier ADRs (validation placement, error shape, contract
generation) because they interlock: the validation decision directly constrains
what the generated contract can express.

---

## Part 1 — Name rules live in the value object

"A name is trimmed, non-blank, at most 255 characters, contains no `/` and no
control characters" is one rule. Laravel wants it in a Form Request; clean
layering wants it in the `NodeName` value object. Writing it in both places is a
DRY violation that will drift. This is the classic Laravel-plus-layering
tension and it needs an explicit answer.

**Decision:** `CreateNodeRequest` declares **shape only** (`name` present and a
string; `parent_id` present and an integer; `type` in the enum). `NodeName` owns
every business rule and throws `InvalidNodeName`. `DomainExceptionRenderer`
converts that into Laravel's standard 422 field-error shape, so the response is
identical to what a Form Request would have produced.

`InvalidNodeName` **carries the field name it relates to** as a property, set
where the value object is constructed. Without that, the renderer would have to
hardcode `"name"` — an HTTP concern leaking backward into the class whose entire
job is to stop that happening.

**Consequence:** one definition of a valid name, holding for the seeder and
console commands as well as HTTP. One layer of indirection is introduced, so a
feature test asserting a blank name still returns a 422 keyed by `name` is
**required**, not optional.

---

## Part 2 — RFC 9457 for domain errors, native 422 for validation

Two shapes, deliberately, each where it belongs.

**Field validation → Laravel's native 422**, because that is what the framework
emits and what every Laravel-aware consumer expects:

```json
{ "message": "The given data was invalid.",
  "errors": { "name": ["A name cannot be blank."] } }
```

**Everything else → RFC 9457 Problem Details** (`application/problem+json`):

```json
{ "type": "https://example.com/problems/duplicate-node-name",
  "title": "Duplicate name", "status": 409,
  "detail": "A folder named \"Invoices\" already exists here.",
  "code": "DUPLICATE_NODE_NAME" }
```

All mapping happens in `DomainExceptionRenderer`:

| Exception | Status | `code` |
|---|---|---|
| `NodeNotFound` | 404 | `NODE_NOT_FOUND` |
| `DuplicateNodeName` | 409 | `DUPLICATE_NODE_NAME` |
| `ParentIsNotAFolder` | 422 | `PARENT_IS_NOT_A_FOLDER` |
| `RootIsImmutable` | 422 | `ROOT_IS_IMMUTABLE` |
| `MaxDepthExceeded` | 422 | `MAX_DEPTH_EXCEEDED` |
| `InvalidNodeName` | 422 | *rendered as native field errors* |

The `code` field is what lets the UI say "A folder named X already exists here"
instead of "Something went wrong". A test asserts every subclass of the domain
exception base appears in the map, so a future rule cannot leak as a 500.

**Consequence:** deliberately mixing two conventions **requires a paragraph of
justification in the README**, or it reads as inconsistency rather than a
decision.

---

## Part 3 — Generate the contract, generate the TypeScript

The backend decides the JSON shape; the frontend needs a TypeScript type for
the same shape. Hand-writing both is DRY across a language boundary, where no
compiler can see both sides — TypeScript trusts a stale type and the bug appears
at runtime, in the browser, long after the change that caused it.

```
  Laravel controllers + API resources     ← single source of truth
            │  Scramble
            ▼
     openapi.json (OpenAPI 3.1)           ← committed, served at /docs/api
            │  openapi-typescript
            ▼
  web/src/api/generated/types.ts          ← committed, NEVER hand-edited
```

`npm run api:types` regenerates. CI fails if the committed output differs.

### The tension with Part 1 — and how it is resolved

Scramble infers request schemas from Form Request rules. Part 1 deliberately
strips those rules down to shape, so **the generated OpenAPI would document
`name: string` with no length limit, no character restrictions, and no 422
field-error response** — because those rules live in `NodeName`, where no static
analyzer will find them. The "single source of truth" would be materially
incomplete about the one endpoint with interesting validation.

**Resolution:** `CreateNodeRequest` carries explicit Scramble annotations
describing the name constraints and the 422 response. This is the annotation
burden that generation costs, and it exists specifically because of Part 1.
The annotations are documentation of the value object's rules, so a test asserts
the documented `maxLength` matches `NodeName::MAX_LENGTH` — otherwise the
annotation is just a second place for the rule to drift, which is what Part 1
set out to prevent.

**Alternatives rejected:** a hand-written OpenAPI spec (moves the DRY problem
into a third file rather than solving it); hand-written TypeScript types (the
textbook cross-boundary DRY violation).
