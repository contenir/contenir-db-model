# Upgrading from 1.x to 2.0

2.0 is a rewrite. The ideas carry over: entities, repositories, lazy
relations, optimistic locking, transactions. But almost every class and
method has changed. This guide maps each 1.x API to its 2.0 equivalent.
Work through it top to bottom.

## Requirements

| | 1.x | 2.0 |
| --- | --- | --- |
| PHP | ^8.1 | 8.3, 8.4 or 8.5 |
| Database layer | `laminas/laminas-db` | `php-db/phpdb` 0.6, plus a platform package such as `php-db/phpdb-mysql` |
| Hydration | `laminas/laminas-hydrator` | built in |
| Events | `laminas/laminas-eventmanager` | not used |
| Other | `laminas/laminas-mvc`, `contenir/contenir-metadata` | not needed |

`php-db/phpdb` is the community continuation of laminas-db, under the
`PhpDb\` namespace. Any `Laminas\Db\…` code of yours that talks to this
package (adapters, `Sql\Select`, predicates) moves to `PhpDb\…`. Until
phpdb 0.6.0 is tagged, your project needs `"minimum-stability": "dev"` and
`"prefer-stable": true`.

## 1. Entities

1.x entities extended `AbstractEntity` and listed their columns in arrays.
Values lived in an internal `$data` array behind `__get`/`__set`.

```php
// 1.x
class UserEntity extends AbstractEntity
{
    protected array $primaryKeys = ['id'];
    protected array $columns     = ['id', 'email', 'name', 'created_at', 'version'];
    protected ?string $versionColumn = 'version';
    protected array $relations   = [ /* see section 4 */ ];
}
```

2.0 entities are plain classes with typed properties and attributes:

```php
// 2.0
#[Table('users')]
final class User
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Column]
    public string $email;

    #[Column]
    public ?string $name = null;

    #[Column('created_at')]
    public DateTimeImmutable $createdAt;

    #[Version]
    public int $version = 1;
}
```

| 1.x | 2.0 |
| --- | --- |
| `extends AbstractEntity` / `BaseEntity` | No base class. Add `#[Table('name')]` |
| `$primaryKeys = ['id']` | `#[Id]` on each key property. Add `generated: true` for auto-increment |
| `$columns = [...]` | `#[Column]` on each persisted property. Rename with `#[Column('created_at')]` |
| `$versionColumn = 'version'` | `#[Version]` on a non-nullable `int` property |
| `nextVersion()` override (timestamps etc.) | Removed. Versions are integers incremented by one |
| Column values as untyped `$data` | Typed properties, converted by [type converters](docs/types.md) (`DateTimeImmutable`, enums, `bool`, JSON…) |
| `$entity->column` via `__get`/`__set` | Real properties. **Property names are no longer column names**: `created_at` becomes `$createdAt` with `#[Column('created_at')]` |
| `new UserEntity(['email' => …])` / `populate()` / `exchangeArray()` | `new User()` and assign properties, or write your own constructor. Constructors are not called when loading |
| `getArrayCopy()` | Removed. Build arrays yourself, for example in a `toArray()` method on the entity |
| `getModifiedArrayCopy()` / `markClean()` / `synch()` | Removed. The entity manager tracks changes itself (see section 3) |
| `getPrimaryKeys()`, `getColumns()`, `getRelations()`, `getVersionColumn()` | Read mapping from `EntityMetadata` (see [mapping](docs/mapping.md#reading-metadata)) |
| `EntityInterface`, event manager (`getEventManager()`) | Removed |
| `unset($entity->column)` / `isset()` on columns | Normal PHP property semantics |

**Things to check while converting:**

- **Nullability.** Declare every column that can be NULL as `?type`.
  Loading NULL into a non-nullable property throws
  `TypeConversionException`.
- **`array` columns.** These need `#[Column(type: 'json')]`. Union-typed
  properties need an explicit `type:`.
- **Readonly properties.** `readonly` is fine, except on a generated id or
  the version.
- **Mapping errors.** The mapping is validated, and mistakes throw
  `MappingException` with the class and property named. Build metadata for
  every entity in a test to catch them early:
  `(new AttributeMetadataFactory())->getMetadataFor(User::class)`.
- **Constructors.** Entities are created without calling the constructor
  when loaded. Don't rely on the constructor for state that loaded entities
  need.

### Shared base entities

1.x packages sometimes shipped an abstract entity for applications to
extend. That still works. Declare shared mapped properties on an abstract
class without `#[Table]`, and put `#[Table]` on the concrete class:

```php
abstract class BaseAsset
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Column]
    public string $path;
}

#[Table('assets')]
final class Asset extends BaseAsset {}
```

Inherited properties must be `public` or `protected`; a parent's `private`
properties are not mapped. Entities are **not** container services any
more, so remove registrations such as
`BaseAssetEntity::class => InvokableFactory::class`.

## 2. Repositories and queries

1.x repositories extended `AbstractRepository` (a laminas
`TableGatewayInterface`) and declared their table. 2.0 has one generic
`Repository`, obtained from the entity manager. The table comes from the
entity's `#[Table]`.

```php
// 1.x
class UserRepository extends BaseRepository
{
    protected TableIdentifier|string|array|null $table = 'users';
}
$users = $container->get(UserRepository::class);

// 2.0
$em    = $container->get(EntityManager::class);
$users = $em->getRepository(User::class);
```

| 1.x | 2.0 |
| --- | --- |
| `find($where, $order)` with column-keyed `$where` | `findBy($criteria, $orderBy)`. **Criteria are keyed by property name** |
| `findOne($where, $order)` | `findOneBy($criteria, $orderBy)`, or `find($id)` by primary key |
| `findByField('email', $value)` / `findOneByField(...)` | `findBy(['email' => $value])` / `findOneBy(...)`. Arrays still become `IN (...)` |
| `$where` as a `Closure` or `Sql\Where` | `createSelect()`, modify the `Select`, then `fetch($select)` / `fetchOne($select)` |
| `select()` + `prepareSelect()` + `selectWith($select)` | `createSelect()` + `fetch($select)` |
| `$order` strings like `'name DESC'` | `['name' => 'DESC']`, keyed by property name |
| Repository `$where` / `$order` default properties (implicit scopes) | Removed. Add named methods to a custom repository (below) and use them instead |
| `create($data)` | `new User()` and assign properties |
| Results as a buffered `HydratingResultSet` | `findBy()` returns `list<User>`. `stream()` yields without buffering |
| Two `findOne()` calls → two objects | Same row → **same object**, via the [identity map](docs/identity-map.md) |
| `getHydrator()`, `getResultSet()`, `getSql()`, `getTable()`, `getAdapter()` | Removed. Use phpdb directly for anything outside the mapper |
| `insert($array)`, `update($set, $where)`, `delete($where)` with raw arrays | Removed from repositories. Write through entities (section 3), or use `PhpDb\Sql\Sql` with your adapter for bulk statements |
| `getLastInsertValue()` | The generated id is written onto the entity by `save()` |

Selects you build with `createSelect()` use **column** names in raw phpdb
calls, and must keep the primary-key columns selected.

### Custom repositories

```php
/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, User::class);
    }

    /** @return list<User> */
    public function findActive(): array
    {
        return $this->findBy(['active' => true], ['createdAt' => 'DESC']);
    }
}
```

Register it with `Contenir\Db\Model\Container\RepositoryFactory`. Note the
namespace changed from `Repository\Factory\RepositoryFactory`.

1.x's `RepositoryFactory` guessed the entity from the repository name
(`…\Repository\UserRepository` → `…\Entity\UserEntity`), or read it from
`model.map`. 2.0 has no convention and no map: the repository passes its
entity class to `parent::__construct()`.

A package that ships an **abstract** repository for applications to extend
should forward the class name, and leave the concrete subclass to the
application:

```php
// in the package
abstract class BaseAssetRepository extends Repository {}

// in the application
final class AssetRepository extends BaseAssetRepository
{
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, Asset::class);
    }
}
```

Abstract repositories can't be built by `RepositoryFactory`. Register the
application's concrete class instead.

## 3. Saving, deleting and transactions

Writes moved from the repository to `EntityManager`.

| 1.x | 2.0 |
| --- | --- |
| `$repo->save($entity)` | `$em->save($entity)` |
| `save($entity, MODE_INSERT)` / `MODE_UPDATE` / `MODE_AUTO` | Removed. Entities the manager is tracking are updated; anything else is inserted |
| `save($entity, refresh: true)` | `$em->saveAndRefresh($entity)` |
| Insert vs update decided by "is the primary key null?" | Decided by whether the entity was loaded or saved through this manager. A `new` entity with a natural key is always inserted |
| `$repo->delete($where)` | `$em->delete($entity)` |
| `$repo->synch($entity)` | `$em->refresh($entity)` |
| `$repo->transactional($fn)` | `$em->transactional($fn)`. Still re-entrant, and now also rolls back in-memory tracking |
| `StaleEntityException` on version conflict | Same, also raised for guarded deletes |
| `clearRelationsCache()` | Removed. Use `$em->refresh($entity)` to reset an entity's relations |

**Behaviour changes to be aware of:**

- **Only changed columns are written.** If nothing changed, `save()` sends
  no SQL at all.
- **Long-running processes must clear the manager.** The entity manager
  holds every loaded entity. Workers and long-running servers must call
  `$em->clear()` between jobs or requests (see
  [container](docs/container.md#lifetime-of-the-entity-manager)).
- **Reloading after `clear()`.** An entity loaded before `clear()` is no
  longer tracked, and saving it would insert it again. Load it again after
  clearing.

## 4. Relations

1.x relations were config arrays pointing at a **repository** class. 2.0
uses attributes pointing at the **entity** class.

```php
// 1.x
protected array $relations = [
    'orders' => [
        'type'   => AbstractEntity::RELATION_MANY,
        'column' => 'id',
        'table'  => ['class' => OrderRepository::class, 'column' => 'user_id'],
        'order'  => ['created_at DESC'],
    ],
    'profile' => [
        'type'   => AbstractEntity::RELATION_SINGLE,
        'column' => 'id',
        'table'  => ['class' => ProfileRepository::class, 'column' => 'user_id'],
    ],
    'tags' => [
        'type'   => AbstractEntity::RELATION_MANY,
        'column' => 'id',
        'table'  => ['class' => TagRepository::class, 'column' => 'id'],
        'via'    => ['table' => 'user_tag', 'column' => 'user_id', 'join' => 'tag_id'],
    ],
];

// 2.0
use LazyRelationsTrait;   // only needed for lazy single relations

/** @var Collection<Order> */
#[HasMany(Order::class, foreignKey: 'user_id', orderBy: ['created_at' => 'DESC'])]
public Collection $orders;

#[HasOne(Profile::class, foreignKey: 'user_id')]
public ?Profile $profile;

/** @var Collection<Tag> */
#[ManyToMany(Tag::class, via: new Via('user_tag', foreignKey: 'user_id', relatedKey: 'tag_id'))]
public Collection $tags;
```

| 1.x relation key | 2.0 |
| --- | --- |
| `type => RELATION_MANY` with the foreign key on the target | `#[HasMany(Target::class, foreignKey: …)]` |
| `type => RELATION_SINGLE` with the foreign key on the target | `#[HasOne(Target::class, foreignKey: …)]` |
| `type => RELATION_SINGLE` with the foreign key on this entity | `#[BelongsTo(Target::class, foreignKey: 'this_entity_column')]` |
| `via => [...]` | `#[ManyToMany(Target::class, via: new Via(table, foreignKey: …, relatedKey: …))]`. `column` becomes `foreignKey`, `join` becomes `relatedKey` |
| `column` (this entity) / `table.column` (target) | `localKey` / `foreignKey` (or `ownerKey` for `BelongsTo`). Defaults are the primary keys |
| `table.class` = a repository class | The **entity** class |
| `where` (any predicate) | `where: ['column' => value]`, equality on target columns only |
| `order` strings | `orderBy: ['column' => 'ASC'|'DESC']`, target column names |

**Access changes:**

- **To-many** relations are a `Collection` instead of an array. It's
  iterable and countable, with `toArray()`, `first()` and `isEmpty()`.
  Code doing `foreach` or `count()` keeps working; `$user->orders[0]`
  becomes `$user->orders->first()` or `->toArray()[0]`.
- **A missing single relation** is `null`, where 1.x returned `false`.
  Declare the property nullable.
- **Lazy single relations** need `use LazyRelationsTrait;`. Without it,
  preload them.
- **Relation properties** must have no default value.
- **Preloading:** `preloadRelations($entities, ['orders'])` becomes
  `$repo->preload($entities, 'orders')`. It now also supports `via`
  relations, composite keys and nested paths (`'orders.items'`).
- **Unserialised entities.** Entities serialise cleanly (there's no event
  manager to lose), but an unserialised entity is not managed. Load it
  again to use lazy relations.

See [relations](docs/relations.md).

## 5. Configuration and wiring

| 1.x | 2.0 |
| --- | --- |
| Config key `model.adapter` | `contenir_db_model.adapter` |
| Config key `model.map` | Removed (see custom repositories) |
| `Repository\RepositoryLookup` / `RepositoryLookupFactory` | Removed. Inject `EntityManager` |
| `Repository\Factory\RepositoryFactory` | `Container\RepositoryFactory`, for custom repositories only |
| Entity classes registered as services | Not needed |
| `ConfigProvider` / `Module` | Still provided. They register `EntityManager`, `MetadataFactoryInterface` and `TypeRegistry` |

New options: `contenir_db_model.metadata_cache` (a PSR-16 service, which
you should set in production), `metadata_cache_ttl`, and `types` for custom
type converters. See [container](docs/container.md).

```php
// 1.x
'model' => ['adapter' => 'db.primary', 'map' => [UserRepository::class => UserEntity::class]],

// 2.0
'contenir_db_model' => ['adapter' => 'db.primary', 'metadata_cache' => 'cache.metadata'],
```

## 6. Exceptions

All exceptions still implement `Contenir\Db\Model\Exception\ExceptionInterface`
and extend the package's `InvalidArgumentException` or `RuntimeException`.
There are new, more specific types: `MappingException`, `QueryException`,
`ConfigurationException` (invalid arguments) and `TypeConversionException`,
`HydrationException`, `PersistenceException`, `IdentityConflictException`,
`RelationException`, `StaleEntityException` (runtime). Existing
`catch (ExceptionInterface $e)` blocks keep working.

## Checklist

1. Require PHP 8.3+ and `php-db/phpdb` with your platform package. Move
   `Laminas\Db` imports to `PhpDb`.
2. Convert each entity to attributes and typed properties, and check
   nullability.
3. Write a test that builds metadata for every entity.
4. Replace repository lookups with `$em->getRepository()` or custom
   `Repository` subclasses. Convert `find`/`findByField` calls to property-keyed
   criteria.
5. Move `save`/`delete`/`transactional` calls to the `EntityManager`.
6. Convert relation arrays to attributes, change array access on to-many
   relations, and change `false` checks on single relations to `null`.
7. Update configuration (`model` → `contenir_db_model`), remove entity
   service registrations, and enable the metadata cache.
8. In workers and long-running servers, `clear()` the entity manager
   between jobs.
