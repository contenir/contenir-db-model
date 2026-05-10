# Roadmap

This document is the consolidated plan for what is left to do on the
package after the 2.0-prep commit (`f196587`). It is informed by a
five-perspective review (performance, concurrency, data-modelling, peer
libraries, DX/static-analysis) and the 1-6 items that have already
landed.

The plan is organised by release boundary. Items inside a release are
ordered by leverage and dependency, not by alphabet.

## Already shipped on this branch

These are the v2 prerequisites and are already on the branch.

| Item                                    | Effect                                                                                                                         |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Opt-in `save(refresh: true)`            | Default save now does one statement and applies the auto-PK locally. Refresh path wraps INSERT+SELECT in a transaction.         |
| `transactional(callable)`               | Re-entrant transaction helper on `AbstractRepository`. Detects nesting via `Connection::inTransaction()`.                       |
| Optimistic locking                       | Entities declare `protected ?string $versionColumn`. UPDATE adds version to WHERE, bumps via `nextVersion()`, throws `StaleEntityException` on conflict. |
| `getHydrator()` memoisation              | Same `AggregateHydrator` reused for the repository's lifetime; the `RelationsHydrator` FK cache spans every query, not just one result set. |
| Cache invalidation on writes             | `insert/update/delete` clear the repository's RelationsHydrator cache so subsequent reads pick up new state.                    |
| Null-aware PK mode detection             | `save()` only treats `null` as "unsaved". Legacy `id = 0` rows are no longer re-INSERTed every save.                            |
| `@template T of EntityInterface`         | `findOne`/`find`/`findByField` etc. now type-infer correctly under PHPStan/Psalm/PhpStorm.                                       |
| `findByField` validates field name        | Caller-supplied column names are checked against the entity prototype.                                                          |
| `findByField` accepts array `$value`      | Becomes `WHERE … IN (…)` automatically.                                                                                          |
| `Sql\Expression` no longer wraps `$order` | `$order` passes through to `Select::order()`, which quotes identifiers.                                                          |
| `executeInsert/Update/Delete` table-match | Normalises `string`/`array`/`TableIdentifier` in the guard so equivalent forms don't trip it.                                    |
| Relations N+1 mitigation                  | Per-FK cache + opt-in `preloadRelations()` for batched eager loading.                                                           |

## 2.0 — backwards-incompatible cleanup

Group these together so the major-version bump pays for itself. Each
item has a clear migration story.

### 2.0.1 — Drop `EventManagerAwareInterface` from `EntityInterface`

`EntityInterface` currently extends `EventManagerAwareInterface`. The
practical cost: every consumer's domain object inherits a
`setEventManager`/`getEventManager` pair plus six EM-related properties,
which leak into `var_dump`, IDE completion, and the public surface.
Worse: serialised entities lose their event manager and the lazy-load
listener silently disappears, so `unserialize($cached)` produces an
entity that throws on `$user->orders`.

**Plan**

1. Remove `extends EventManagerAwareInterface` from `EntityInterface`.
2. Move the `loadRelation` event mechanism into `AbstractEntity` as an
   internal concern. Keep `getEventManager()` lazy on `AbstractEntity`
   itself (already done).
3. Add `__wakeup()` on `AbstractEntity`. On wake, re-attach the
   `RelationsHydrator` listener via the prototype's hydrator. The
   prototype has access to the `RepositoryLookup`; cache the listener
   factory on the prototype so wake can call it without the full
   container.
4. Repositories now type against `AbstractEntity` rather than
   `EntityInterface` for the lazy-load API. Or: introduce
   `LazyLoadingEntityInterface extends EntityInterface` that adds the
   relation-loading hook, and the repository depends on the smaller
   interface.

**Migration**: callers writing custom `EntityInterface` implementations
without inheriting from `AbstractEntity` get a smaller required surface
(no event-manager methods). Any consumer poking at the entity's event
manager directly (rare) needs to switch to `AbstractEntity::getEventManager()`.

**Estimated effort**: half a day. Test the wake-up path explicitly with
a serialise/unserialise round-trip that touches a relation.

### 2.0.2 — Delete `BaseEntity`; fold `BaseRepository` into `AbstractRepository`

`BaseEntity` has no columns and no purpose; you can't usefully
instantiate it. `BaseRepository` is also not a usable default — its
constructor throws unless you subclass and set `$table` — but adds
indirection. Make `findOne()` and `create()` non-abstract on
`AbstractRepository`, delete `BaseEntity` entirely, and let users
extend `AbstractRepository` directly with whatever entity they want.

**Plan**

1. Move `BaseRepository::findOne` and `BaseRepository::create` onto
   `AbstractRepository`. Mark the previous abstract declarations as
   non-abstract default implementations.
2. Have the default `create()` instantiate the prototype's class
   reflectively, not always `BaseEntity`.
3. Delete `src/Entity/BaseEntity.php` and `src/Repository/BaseRepository.php`.
4. Delete the corresponding test assets that exist only to make the
   abstract scaffolding instantiable.

**Migration**: any `class FooRepository extends BaseRepository`
becomes `class FooRepository extends AbstractRepository`. Any
`new BaseEntity()` use site already has nothing useful and must be
replaced with a real subclass.

**Estimated effort**: one hour, including doc updates.

### 2.0.3 — Repository property naming convention without string-munging

`RepositoryFactory::getEntityClass()` currently uses
`str_replace('\\Repository\\', '\\Entity\\', ...)` plus a
`Repository` → `Entity` suffix swap. It's anchored, but still
fragile and surprising. Two cleaner options:

- **Option A (preferred)**: have repositories declare the entity class
  on themselves (`protected string $entityClass = UserEntity::class`).
  The factory reads it via reflection. Removes the convention entirely
  in favour of explicitness.
- **Option B**: keep the convention but document it as the fallback only
  when no `model.map` and no `$entityClass` property are set.

Either way, the implicit-by-default behaviour disappears.

**Estimated effort**: one hour.

### 2.0.4 — Tighten `EntityInterface` or admit it's `AbstractEntity`

Current state: `EntityInterface` declares 11 methods, half of which are
implementation-shaped. The honest answer is one of:

- **Narrow**: `EntityInterface` declares only the data accessors
  (`getPrimaryKeys`, `getColumns`, `getRelations`, `getArrayCopy`,
  `getModifiedArrayCopy`). Everything else (`populate`, `synch`,
  `markClean`, version hooks) moves to a separate `MutableEntityInterface`
  or `PersistableEntityInterface`. Repositories depend on the wider
  interface; consumers who only read can depend on the narrow one.
- **Admit and rename**: rename `EntityInterface` to `EntityContract`,
  document it as the union of methods `AbstractRepository` calls, and
  drop the pretence that it's a substitution boundary.

Pick narrow. The cost is one extra interface; the benefit is a
type-safe public surface for read-only consumers (templates, JSON
encoders).

**Estimated effort**: half a day with tests.

### 2.0.5 — Streaming reads via `findStream()`

Every read currently buffers (`HydratingResultSet::buffer()`). Fine for
typical OLTP, but a 100k-row scan OOMs the worker before row 1 reaches
the caller.

**Plan**

1. Add `findStream($where = null, $order = null, ?Sql\Select $select = null): Generator`
   that yields hydrated entities one at a time without `buffer()`.
2. Document that streamed entities cannot use the relation lazy-load
   path (the connection's cursor is busy), so callers must call
   `preloadRelations()` first or hydrate eagerly.
3. Keep `find()` buffered as the safe default.

**Estimated effort**: half a day.

## 2.1 — incremental, additive

These are not breaking, so they ship after 2.0 stabilises.

### 2.1.1 — Multi-level eager loading

`preloadRelations(['orders.items'])` should walk the dot path and
batch-load the inner relation against the outer's children. Eloquent
and Doctrine both do this, and it's the most common reason users hit
N+1 even with explicit eager loading.

**Plan**

1. Parse the dotted path in `preloadRelations`.
2. After loading the first level, recurse: collect FKs from the loaded
   children, call the child repo's `preloadRelations` for the next
   level.
3. Verify cycles fail loudly.

**Estimated effort**: one to two days. Most of the work is
double-checking what happens when a leg of the path has no children.

### 2.1.2 — `via`-table support in `preloadRelations`

Currently `preloadRelations` rejects via-table relations with a clear
error and forces the caller back onto the lazy path. The lazy path
issues one query per parent, which is the worst case.

**Plan**

1. Recognise `via` in the relation config inside `preloadRelations`.
2. Issue one SELECT joining child + via with `WHERE via.{column} IN (parent_pks)`.
3. Group results back to parents.
4. Test against the existing `users / user_tag / tags` fixture.

**Estimated effort**: one day.

### 2.1.3 — Composite-key support in `preloadRelations`

Same as 2.1.2 but for composite-FK relations. The implementation is a
tuple-`IN` (`WHERE (col_a, col_b) IN ((…), (…))`). Most modern engines
support tuple-IN; for those that don't, fall back to a generated
`WHERE (col_a = ? AND col_b = ?) OR …`.

**Estimated effort**: half a day.

### 2.1.4 — `RepositoryInterface::reload($entity)`

Add an explicit reload-from-storage helper so callers with a known-stale
entity instance don't have to drop down to `$repo->synch($entity)`.
Same implementation, friendlier name.

**Estimated effort**: 15 minutes including a test.

### 2.1.5 — Better error message on `findOne($scalar)`

Today, `$repo->findOne(42)` returns garbage (laminas treats the int as
a literal predicate). Detect a non-array-non-Closure scalar `$where`
in `find/findOne/findByField/prepareSelect` and throw
`InvalidArgumentException` with a hint to use
`findOne(['id' => 42])` or `findOneByField('id', 42)`.

**Estimated effort**: 30 minutes.

## 3.0 — vision-level rearchitecture

These items would shift the package's design. They're worth doing only
if the broader appetite is "make this a serious thin data layer".
Otherwise skip.

### 3.0.1 — Split column storage from relation storage

Current state (`AbstractEntity`): one `$data` array holds both columns
and relations; `getModifiedArrayCopy()` filters relations out via
`array_diff_key`; lazy-loading uses a `null`/`false` sentinel because
there's no separate "loaded" flag.

**Plan**

1. Introduce `protected array $relationData = []` for relation slots.
2. Introduce `protected array $relationsLoaded = []` (string => bool)
   so the lazy-load path uses an explicit "is loaded" flag instead of
   a `null` / `false` value sentinel.
3. `__get` checks columns first, then relations; relation access reads
   from `$relationData` and triggers loading via `$relationsLoaded`.
4. `getArrayCopy()` returns columns only (callers that wanted
   relations get a new `getRelationData()`).
5. `getModifiedArrayCopy()` no longer needs `array_diff_key` against
   relations — the storage shape already excludes them.

**Migration**: any code reading `$entity->getArrayCopy()` and seeing
`['id' => 1, 'orders' => [...]]` now gets only `['id' => 1]`. Templates
and JSON encoders that relied on the joint dump need to be updated.

**Estimated effort**: two to three days, much of it test/migration
work.

### 3.0.2 — Relations off `__get`, into explicit accessors

Once 3.0.1 is in place, the relation taxonomy can move to typed
accessors. Two flavours:

- **Hand-rolled**: each entity declares
  `public function orders(): array { return $this->loadRelation('orders'); }`.
  Verbose but typed.
- **Generated**: a build-time generator reads `$relations` and writes
  per-relation accessor stubs (`UserEntity_Relations` trait or
  partial-class shim). Concise and typed.

Either way, `__get` keeps its column-only role and IDE/static analysis
get useful return types for relations.

**Estimated effort**: two days for hand-rolled (most of it doc); a week
for generated, including the build step.

### 3.0.3 — Identity map at `RepositoryLookup`

Two `findOne(['id' => 1])` calls today produce two distinct entity
instances. For non-trivial domain logic, this is a real footgun — see
the data-modelling and concurrency reviews. Promote `RepositoryLookup`
from "container handle" to "request-scoped object cache":

1. After hydration, the lookup is asked "do you already have a
   `UserEntity` keyed by `id = 1`? if so, return that; otherwise store
   this one".
2. `save()`/`delete()` invalidate corresponding entries.
3. Lifetime: explicitly per-request (or per-transaction). Long-running
   workers must reset the cache between iterations or risk staleness.

**Migration**: callers that compared two loaded entities by reference
silently start agreeing where they previously diverged. Anyone relying
on per-load instances (rare) needs to clone.

**Estimated effort**: a week, plus thorough concurrency tests.

### 3.0.4 — Drop magic `__get`/`__set` for typed properties

The most aggressive option: stop using `__get`/`__set` entirely.
Entities declare typed properties; a hydrator reads/writes them via
reflection or generated accessors. PHPStan/Psalm understand the entity
natively, IDE autocomplete works, and serialise/unserialise is plain.

This is essentially "go full Atlas / Cycle". It's a lot of work but
gets the package out of the 2010-PHP idiom for good.

**Estimated effort**: three to four weeks. Probably best paired with
3.0.3.

### 3.0.5 — Snapshot reads / consistent preload

`preloadRelations()` reads the parent and child sets in two
non-atomic statements. Under concurrent writes the resulting view may
correspond to no point-in-time database state. Fix by wrapping the
preload in a `transactional()` (already available — just call it from
within), and consider exposing isolation-level options on
`AbstractRepository`.

**Estimated effort**: a day for the wrap, a week for proper
isolation-level support.

## Items deliberately left out

- **Migrations / schema management**: outside the package's scope.
  Phinx and `doctrine/migrations` exist standalone.
- **CLI scaffolding**: low value for the niche.
- **DBAL-level abstraction**: the package's premise is to interoperate
  with `Laminas\Db\Sql`. Adding a parallel builder undermines that.
- **Event sourcing / CQRS hooks**: a different product.

## Suggested release sequence

1. Land `f196587` and the rest of this branch as the start of a `2.0.0`
   line. Tag `2.0.0-rc.1` once the items in 2.0 (above) are merged.
2. Iterate 2.1.x as additive features land.
3. Open a `3.0` branch when the appetite is clearly there for the
   bigger rearchitecture (3.0.1 is the gate — if you don't want to
   split storage, the rest of 3.0 doesn't make sense).
