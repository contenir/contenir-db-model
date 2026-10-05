# contenir-db-model

A small data mapper for [php-db/phpdb](https://github.com/php-db/phpdb).
Entities are plain PHP classes with typed properties, described by
attributes. There are no base classes and no magic `__get`/`__set`.

> **Status: 2.0 in development.** Version 2 is a rewrite and is not
> compatible with 1.x. The sections below describe only what has landed so
> far. 1.x remains available from the `v1.0.x` tags.

## Requirements

- PHP 8.3, 8.4 or 8.5
- `php-db/phpdb` 0.6 (`0.6.x-dev` until 0.6.0 is tagged), plus the platform
  package for your database, such as `php-db/phpdb-mysql` or
  `php-db/phpdb-sqlite`
- `psr/container`, `psr/simple-cache`

## Installation

```bash
composer require contenir/contenir-db-model:^2.0@dev
```

While phpdb 0.6 is untagged, the consuming project needs
`"minimum-stability": "dev"` and `"prefer-stable": true`.

With `laminas/laminas-component-installer`, the `ConfigProvider` is
registered automatically. See [container integration](docs/container.md)
for configuration.

## At a glance

```php
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

    #[HasMany(Order::class, foreignKey: 'user_id')]
    public iterable $orders;
}

$em    = new EntityManager($adapter);
$users = $em->getRepository(User::class);

$user        = $users->find(1);
$user->email = 'new@example.com';
$em->save($user);   // UPDATE users SET email = ?, version = 2 WHERE id = 1 AND version = 1
```

## Documentation

| Topic | Status |
| --- | --- |
| [Mapping entities](docs/mapping.md): attributes, keys, relations, validation | Available |
| [Metadata caching](docs/metadata-caching.md): PSR-16 cache for mapping metadata | Available |
| [Type conversion](docs/types.md): built-in converters, resolution order, custom converters | Available |
| [Entity lifecycle](docs/entity-lifecycle.md): hydration, refresh, change tracking, writing entity classes | Available |
| [Sensitive data](docs/sensitive-data.md): `SensitiveString` and `#[Column(sensitive: true)]` | Available |
| [Identity map](docs/identity-map.md): one object per row, matching rules, memory in long-running processes | Available |
| [Persisting entities](docs/persistence.md): `EntityManager` save, delete, refresh, optimistic locking, transactions | Available |
| [Repositories and finders](docs/repositories.md): `find`, criteria, ordering, streaming, custom queries and repositories | Available |
| [Container integration](docs/container.md): `ConfigProvider`, factories, configuration, entity manager lifetime | Available |
| Relation loading and `preload()` | Planned |
| Upgrading from 1.x | Planned |

[`llms.txt`](llms.txt) indexes these pages for LLM tooling.

## Development

Mago is required. It is a standalone binary, installed with `brew install
mago` or see the [Mago docs](https://mago.carthage.software/).

```bash
composer install
composer check              # format check, lint, static analysis, unit + integration tests
composer cs-fix             # apply formatting and safe lint fixes
composer test               # unit suite (tests/Unit)
composer test-integration   # integration suite against in-memory SQLite (tests/Integration)
composer test-coverage      # clover.xml (needs Xdebug or PCOV)
```

QA configuration comes from
[php-db/phpdb-qa-tools](https://github.com/php-db/phpdb-qa-tools).

## License

BSD-3-Clause.
