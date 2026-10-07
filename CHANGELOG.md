# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0-rc4] - Unreleased

### Added

- **Ordering many-to-many relations by join-table columns.** `Via` takes an
  `orderBy` of join-table column => `ASC`/`DESC`, such as a link's
  `sequence`, applied before the relation's own `orderBy` on target columns
  for lazy collections and `preload()` alike. Columns must be plain names
  and directions `ASC` or `DESC`, checked by `MappingException`. 1.x
  `order` strings naming the `via` table map to this. `JoinTable` gains a
  matching `orderBy`. Relations without it are unchanged.

### Changed

- Relation `orderBy` docblock types on `HasOne`, `HasMany`, `ManyToMany` and
  `Via` now accept lower-case `'asc'`/`'desc'`, matching the runtime, which
  has always treated directions case-insensitively.
- `CachedMetadataFactory` keys use the prefix
  `contenir.db-model.metadata.v2.`, as cached `JoinTable` metadata changed
  shape. Entries cached by rc3 and earlier are ignored and rebuilt.

## [2.0.0] - Unreleased

A ground-up rewrite on `php-db/phpdb`. **Not compatible with 1.x**: see
[UPGRADE-2.0.md](UPGRADE-2.0.md).

### Added

- **Attribute mapping.** `#[Table]`, `#[Column]`, `#[Id]`, `#[Version]`,
  `#[HasOne]`, `#[HasMany]`, `#[BelongsTo]` and `#[ManyToMany]` with
  `Via`. Plain entities with typed properties, with mapping validated up
  front by `MappingException`.
- **`CachedMetadataFactory`.** PSR-16 caching of mapping metadata. Any
  `psr/simple-cache` 1.x, 2.x or 3.x implementation works, so it installs
  alongside laminas-cache 3, which only provides 1.x.
- **`TypeRegistry`.** Built-in converters for int, float, string, bool,
  datetime, date, json, backed enums and `SensitiveString`, plus custom
  converters.
- **`SensitiveString` and `#[Column(sensitive: true)]`.** Keep secrets out
  of debug output and exception messages.
- **`EntityManager`.**
  - `save`, `saveAndRefresh`, `delete`, `refresh`, `transactional`,
    `contains`, `clear`.
  - Changed-column updates, generated-key write-back, optimistic locking on
    updates and deletes.
  - In-memory tracking is rolled back with the transaction.
- **Identity map.** One object per row per entity manager.
- **`Repository`.**
  - `find` (with no query for an entity that's already loaded), `findOneBy`,
    `findBy`, `count` and an unbuffered `stream`.
  - `createSelect`, `fetch` and `fetchOne` for custom phpdb selects.
  - Property-keyed, validated criteria.
  - `find`, `findOneBy`, `findBy` and `stream` accept an optional base
    `Select` (cloned, with criteria and order columns qualified by table),
    for joins and complex predicates.
- **Relations.**
  - Lazy, read-only `Collection` for to-many relations.
  - `LazyRelationsTrait` for to-one relations.
  - `Repository::preload()` with nested paths, join tables and composite
    keys, in one query per relation level.
- **Container wiring.** `ConfigProvider`, a laminas-mvc `Module`, PSR-11
  factories and `RepositoryFactory`, configured under `contenir_db_model`.
- **Documentation** in `docs/`, plus an `llms.txt` index.
- **QA.** Mago formatting, linting and static analysis via
  `php-db/phpdb-qa-tools`, and PHPUnit 11 unit and SQLite integration
  suites. `composer test-coverage-branches` produces branch coverage.

### Changed

- Requires PHP 8.3+ and `php-db/phpdb` 0.6 instead of `laminas/laminas-db`.
- Configuration key `model` is now `contenir_db_model`.
- A missing single relation is `null`, no longer `false`.

### Removed

- `AbstractEntity`, `BaseEntity`, `EntityInterface` and magic
  `__get`/`__set` entities.
- `AbstractRepository`, `BaseRepository` and their table-gateway methods.
- `EntityHydrator`, `RelationsHydrator`, `RepositoryLookup` and the
  repository-to-entity naming convention.
- Dependencies on `laminas/laminas-db`, `laminas/laminas-hydrator`,
  `laminas/laminas-eventmanager`, `laminas/laminas-mvc` and
  `contenir/contenir-metadata`.

## 1.x

See the `v1.0.*` tags.

[2.0.0-rc4]: https://github.com/contenir/contenir-db-model/compare/v2.0.0-rc3...main
[2.0.0]: https://github.com/contenir/contenir-db-model/compare/v1.0.4.4...v2
