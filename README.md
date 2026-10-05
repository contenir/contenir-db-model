# contenir-db-model

A small data mapper for [php-db/phpdb](https://github.com/php-db/phpdb).
Entities are plain PHP classes with typed properties, described by
attributes. There are no base classes and no magic `__get`/`__set`.

- **Attribute mapping.** Tables, columns, keys, versions and relations are
  declared on the class and validated up front.
- **Typed values.** Enums, dates, JSON, booleans and secrets are converted
  in both directions.
- **An entity manager** with an identity map, changed-column updates,
  optimistic locking and re-entrant transactions.
- **Repositories** with validated criteria, streaming and custom phpdb
  selects.
- **Lazy relations,** plus `preload()` to avoid N+1 queries.

> **Status: 2.0 pre-release.** 2.0 is a rewrite and is not compatible with
> 1.x. See [UPGRADE-2.0.md](UPGRADE-2.0.md). 1.x remains available from the
> `v1.0.*` tags.

## Requirements

- PHP 8.3, 8.4 or 8.5
- `php-db/phpdb` 0.6, plus the platform package for your database, such as
  `php-db/phpdb-mysql` or `php-db/phpdb-sqlite`
- `psr/container`, `psr/simple-cache`

## Installation

```bash
composer require contenir/contenir-db-model:^2.0@RC
```

The latest development version is `v2.x-dev`. Until `php-db/phpdb` 0.6.0
and this package's 2.0.0 are tagged, the consuming project needs `"minimum-stability": "dev"` and
`"prefer-stable": true`.

With `laminas/laminas-component-installer`, the `ConfigProvider` (Mezzio)
or `Module` (laminas-mvc) is registered automatically. See
[container integration](docs/container.md) for configuration.

## At a glance

```php
use Contenir\Db\Model\Collection;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Mapping\{Column, HasMany, Id, Table, Version};

#[Table('users')]
final class User
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Column]
    public string $email;

    #[Column('created_at')]
    public DateTimeImmutable $createdAt;

    #[Version]
    public int $version = 1;

    /** @var Collection<Order> */
    #[HasMany(Order::class, foreignKey: 'user_id')]
    public Collection $orders;
}

$em    = new EntityManager($adapter);
$users = $em->getRepository(User::class);

$user        = $users->find(1);
$user->email = 'new@example.com';
$em->save($user);   // UPDATE users SET email = ?, version = 2 WHERE id = 1 AND version = 1

foreach ($user->orders as $order) {
    // loaded lazily, in one query
}
```

## Documentation

Read in this order:

1. [Mapping entities](docs/mapping.md): attributes, keys, validation rules
2. [Type conversion](docs/types.md): built-in and custom converters, nulls
3. [Persisting entities](docs/persistence.md): `EntityManager` saves,
   deletes, locking and transactions
4. [Repositories and finders](docs/repositories.md): criteria, streaming,
   custom queries and repositories
5. [Relations](docs/relations.md): lazy collections, `LazyRelationsTrait`,
   `preload()`
6. [Identity map](docs/identity-map.md): one object per row, and memory in
   long-running processes
7. [Entity lifecycle](docs/entity-lifecycle.md): hydration, refresh and
   change tracking in detail
8. [Sensitive data](docs/sensitive-data.md): `SensitiveString` and
   redaction
9. [Metadata caching](docs/metadata-caching.md): PSR-16 cache for
   production
10. [Container integration](docs/container.md): configuration, factories,
    entity manager lifetime

Upgrading from 1.x: [UPGRADE-2.0.md](UPGRADE-2.0.md). Changes:
[CHANGELOG.md](CHANGELOG.md).

[`llms.txt`](llms.txt) indexes these pages and lists the key rules for LLM
tooling.

## Development

Mago is required. It is a standalone binary, installed with `brew install
mago` or see the [Mago docs](https://mago.carthage.software/).

```bash
composer install
composer check                    # format check, lint, static analysis, unit + integration tests
composer cs-fix                   # apply formatting and safe lint fixes
composer test                     # unit suite (tests/Unit)
composer test-integration         # integration suite against in-memory SQLite (tests/Integration)
composer test-coverage            # line coverage to clover.xml (Xdebug or PCOV)
composer test-coverage-branches   # line + branch coverage across both suites (Xdebug)
```

The integration suite runs against in-memory SQLite by default. To run it
against MySQL or PostgreSQL, set `DB_PLATFORM` (and `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, `DB_PASSWORD` as needed). `compose.yml` starts both
databases:

```bash
docker compose up -d
DB_PLATFORM=mysql DB_PORT=33306 DB_PASSWORD=secret composer test-integration
DB_PLATFORM=pgsql DB_PORT=55432 DB_USER=postgres DB_PASSWORD=secret composer test-integration
```

Every test recreates the fixture schema, including a `crm` schema or
database, so use a disposable database and a user allowed to create it.
CI runs the suite on SQLite, MySQL 8.4 and PostgreSQL 17.

`test-coverage-branches` runs each test directory in its own process and
merges the results, because Xdebug 3.4's `--path-coverage` intermittently
crashes on long runs. Pass `-- --clover clover.xml` or
`-- --html build/coverage` for report files.

QA configuration comes from
[php-db/phpdb-qa-tools](https://github.com/php-db/phpdb-qa-tools).

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
