# contenir-db-model

A small Laminas-flavoured data-mapper layer that pairs immutable-ish entities
with table-backed repositories. Built on top of `laminas-db`,
`laminas-hydrator` and `laminas-mvc`, it provides:

- A `Contenir\Db\Model\Entity\AbstractEntity` base class with column metadata,
  modification tracking, lazy relation loading via `laminas-eventmanager`, and
  array hydration.
- A `Contenir\Db\Model\Repository\AbstractRepository` table-gateway base class
  that wraps `laminas-db` `Sql` operations (`insert`, `update`, `delete`,
  `select`, `find`, `findByField`, `findOne`) and an opinionated `save()`
  helper that auto-detects insert vs. update from the entity's primary keys.
- A `Contenir\Db\Model\Hydrator\RelationsHydrator` that can hydrate single or
  many-related entities, optionally through an intermediate join table.
- Service-manager wiring (`ConfigProvider`, `Module`) so the package is usable
  out of the box from a `laminas-mvc` or Mezzio application.

## Requirements

- PHP `^8.1`
- `laminas/laminas-db` `^2.20`
- `laminas/laminas-hydrator` `^4.15`
- `laminas/laminas-mvc` `^3.0`
- `contenir/contenir-metadata` `^1.0`

## Installation

```bash
composer require contenir/contenir-db-model
```

If you use the Laminas component installer the package will register itself
automatically. Otherwise add the module to your application config:

```php
// config/modules.config.php
return [
    // ...
    'Contenir\\Db\\Model',
];
```

The component exposes a configured database adapter alias under the
`model.adapter` config key (defaulting to `Laminas\Db\Adapter\Adapter`).
Override this in your application config if you use a custom adapter service.

```php
// config/autoload/db.global.php
return [
    'model' => [
        'adapter' => 'My\\Custom\\Adapter',
        'map'     => [
            // Optional repository → entity mapping. Used by RepositoryFactory.
            App\Model\Repository\UserRepository::class => App\Model\Entity\UserEntity::class,
        ],
    ],
];
```

## Usage

### 1. Define an entity

Subclass `AbstractEntity` and declare column metadata. `primaryKeys`,
`columns`, and `relations` are the three properties the base class reads.

```php
use Contenir\Db\Model\Entity\AbstractEntity;

class UserEntity extends AbstractEntity
{
    protected array $primaryKeys = ['id'];

    protected array $columns = [
        'id',
        'email',
        'name',
        'created_at',
    ];

    protected array $relations = [
        'orders' => [
            'type'   => AbstractEntity::RELATION_MANY,
            'column' => 'id',
            'table'  => [
                'class'  => OrderRepository::class,
                'column' => 'user_id',
            ],
            'order'  => ['created_at DESC'],
        ],
    ];
}
```

Entities support array-style construction, isset/unset, modification tracking
and PHP serialisation:

```php
$user        = new UserEntity(['id' => 1, 'email' => 'a@example.com']);
$user->name  = 'Alice';

$user->getModifiedArrayCopy(); // ['name' => 'Alice', ...]
$user->getArrayCopy();         // full row, including null columns
$user->getPrimaryKeys();       // ['id' => 1]
```

### 2. Define a repository

Subclass `BaseRepository` (or `AbstractRepository` for full control) and set
the table name. The factory wires the adapter, entity prototype and lookup
service for you.

```php
use Contenir\Db\Model\Repository\BaseRepository;
use Laminas\Db\Sql\TableIdentifier;

class UserRepository extends BaseRepository
{
    protected TableIdentifier|string|array|null $table = 'users';
}
```

Register the repository with `RepositoryFactory`:

```php
// config/autoload/dependencies.global.php
use Contenir\Db\Model\Repository\Factory\RepositoryFactory;

return [
    'dependencies' => [
        'factories' => [
            App\Model\Repository\UserRepository::class => RepositoryFactory::class,
        ],
    ],
];
```

By default `RepositoryFactory` resolves the entity by anchoring the
convention to the trailing class name and the `\Repository\` namespace
segment, so `App\Model\Repository\UserRepository` resolves to
`App\Model\Entity\UserEntity`. Unrelated occurrences of "Repository"
elsewhere in the namespace are left alone. Override the convention with
the `model.map` config when your naming differs.

### 3. Query and persist

```php
/** @var UserRepository $users */
$users = $container->get(UserRepository::class);

$user = $users->findOne(['email' => 'a@example.com']);

$user->name = 'Updated';
$users->save($user); // detects update vs insert from primary keys

$new = $users->create(['email' => 'b@example.com', 'name' => 'Bob']);
$users->save($new);  // mode auto → insert; primary key is back-filled

$users->delete(['id' => 42]);
```

`findByField($column, $value)` validates `$column` against the
declared columns of the entity prototype and rejects unknown values, so
caller-controlled column names cannot smuggle SQL into the predicate's
left-hand side.

`$order` arguments to `find`, `findByField`, and `prepareSelect` are
passed straight to `Laminas\Db\Sql\Select::order()`, which quotes
identifiers. Pass either a string (`'name ASC'`), a list
(`['name', 'created_at DESC']`), or an associative array
(`['name' => 'ASC']`). For raw SQL fragments, pass a
`Laminas\Db\Sql\Expression` instance explicitly.

`save()` accepts an explicit mode if you need to override the detection:

```php
use Contenir\Db\Model\Repository\AbstractRepository;

$users->save($user, AbstractRepository::MODE_INSERT);
$users->save($user, AbstractRepository::MODE_UPDATE);
```

### 4. Lazy-loaded relations

Relations declared on the entity are fetched on first access. Internally the
entity emits a `loadRelation` event; `RelationsHydrator` attaches a listener
that pulls the related rows from the configured repository.

```php
$user->orders; // triggers a SELECT on the orders repository
```

For many-to-many relationships, declare a `via` table:

```php
'tags' => [
    'type'   => AbstractEntity::RELATION_MANY,
    'column' => 'id',
    'table'  => [
        'class'  => TagRepository::class,
        'column' => 'id',
    ],
    'via' => [
        'table'  => 'user_tag',
        'column' => 'user_id', // column on the join table matching the owning row's key
        'join'   => 'tag_id',  // column on the join table matching the related table's key
    ],
],
```

## Exceptions

All exceptions thrown by the package implement
`Contenir\Db\Model\Exception\ExceptionInterface`. Concrete classes extend the
matching SPL exception:

- `Contenir\Db\Model\Exception\InvalidArgumentException`
- `Contenir\Db\Model\Exception\RuntimeException`

## Development

```bash
composer install
composer test            # run the unit and integration tests
composer cs-check        # check coding standards
composer cs-fix          # apply coding standards fixes
composer test-coverage   # generate clover.xml coverage report (needs xdebug or pcov)
```

The integration tests live under `test/Integration/` and use an in-memory
SQLite database (via `pdo_sqlite`) so they run without external setup. Unit
tests live alongside the corresponding `src` directory under `test/`.

## License

Released under the [BSD-3-Clause](LICENSE) license.
