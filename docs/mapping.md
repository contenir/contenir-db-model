# Mapping entities

Entities are plain PHP classes. They need no base class, interface or magic
methods. A class becomes an entity when it carries `#[Table]`. Its persisted
state is whichever properties carry `#[Column]`, `#[Id]` or `#[Version]`.
Relations to other entities are declared with one relation attribute per
property.

All attributes live in `Contenir\Db\Model\Mapping`.

```php
use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\HasOne;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\ManyToMany;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Version;
use Contenir\Db\Model\Mapping\Via;

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
    #[HasMany(Order::class, foreignKey: 'user_id', orderBy: ['placed_at' => 'desc'])]
    public Collection $orders;

    #[HasOne(Profile::class, foreignKey: 'user_id')]
    public ?Profile $profile;   // no default value: see relations

    /** @var Collection<Tag> */
    #[ManyToMany(Tag::class, via: new Via('user_tag', foreignKey: 'user_id', relatedKey: 'tag_id'))]
    public Collection $tags;

    public string $notPersisted = '';
}

#[Table('orders')]
final class Order
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Column('user_id')]
    public int $userId;

    #[BelongsTo(User::class, foreignKey: 'user_id')]
    public User $user;
}
```

Relation properties are typed `Collection` (to-many) or the target class
(to-one) and must not declare a default value. Loading, lazy access and
`preload()` are covered in [relations](relations.md).

## Class attribute

### `#[Table(name, schema: null)]`

Marks the class as an entity stored in table `name`, optionally qualified by
`schema`. The class must not be abstract. `#[Table]` is not inherited, so
put it on each concrete entity.

Mapped properties may have any visibility. They may also come from a parent
class, as long as they are public or protected there. A parent's private
properties are not visible to the concrete class, so they are not mapped.

## Column attributes

A property is column-mapped when it has at least one of `#[Column]`, `#[Id]`
or `#[Version]`.

### `#[Column(name: null, type: null, sensitive: false)]`

- `name`: the column name. It defaults to the property name exactly as
  written. No snake_case conversion happens, so `createdAt` needs
  `#[Column('created_at')]` to reach a `created_at` column.
- `type`: the name of a registered [type converter](types.md). It defaults to the
  property's declared type. It is required when the property has a union or
  intersection type, such as `#[Column(type: 'json')] public array|string $meta;`.
- `sensitive`: keeps the column's values out of this library's exception
  messages and output. Properties typed `SensitiveString` are sensitive
  automatically. See [sensitive data](sensitive-data.md).

### `#[Id(generated: false)]`

Marks the property as part of the primary key. Use it on several properties
for a composite key. Set `generated: true` when the database assigns the
value on insert (auto-increment or identity). A generated key must be the
only `#[Id]` on the entity.

`#[Id]` on its own maps a column named after the property. Combine it with
`#[Column]` to rename the column:

```php
#[Id]
#[Column('group_id')]
public int $groupId;
```

### `#[Version]`

Opts the entity into optimistic locking. The property must be typed as a
non-nullable `int`. Updates then check the loaded version and increment it.
An update that matches no row throws `StaleEntityException`. At most one
`#[Version]` is allowed per entity, and it cannot also be an `#[Id]`.

### Readonly properties

Mapped properties may be `readonly`, except a generated `#[Id]` or a
`#[Version]`, because the library writes those back after a save.

## Relation attributes

Key columns are always **column names**, not property names. Every key
parameter accepts a string or a list of strings for composite keys. Local
and target key lists must have the same length and are paired by position.

| Attribute | Meaning | Key parameters (defaults in brackets) |
| --- | --- | --- |
| `#[HasOne(Target::class, foreignKey:, localKey:)]` | One target row points back at this entity | `foreignKey` is on the target table. `localKey` is on this table [this entity's primary key]. |
| `#[HasMany(Target::class, foreignKey:, localKey:, orderBy:, where:)]` | Many target rows point back at this entity | Same keys as `HasOne`. |
| `#[BelongsTo(Target::class, foreignKey:, ownerKey:)]` | This entity holds a foreign key to one target row | `foreignKey` is on this table. `ownerKey` is on the target table [the target's primary key]. |
| `#[ManyToMany(Target::class, via: new Via(...), orderBy:, where:)]` | Rows linked through a join table | See `Via` below. |

### `new Via(table, foreignKey:, relatedKey:, localKey: null, targetKey: null, orderBy: [])`

- `table`: the join table.
- `foreignKey`: the join-table column(s) referencing this entity's `localKey`
  [this entity's primary key].
- `relatedKey`: the join-table column(s) referencing the target's `targetKey`
  [the target's primary key].
- `orderBy`: `['column' => 'ASC'|'DESC']` on the **join table**, such as a
  position stored on the link. The direction is case-insensitive. It is applied before the relation's own
  `orderBy`, which then breaks ties. See
  [relations](relations.md#ordering-by-join-table-columns).

### Fixed criteria

`HasOne`, `HasMany` and `ManyToMany` accept:

- `where`: `['column' => value]` equality conditions on the target table,
  applied every time the relation loads. For example, `['active' => true]`.
- `orderBy`: `['column' => 'ASC'|'DESC']` on the target table. The direction
  is case-insensitive and normalised to upper case.

Every column named in `where` and `orderBy` must be mapped on the target.

## Validation

Mapping is validated when metadata is first built. Invalid mappings throw
`Contenir\Db\Model\Exception\MappingException`, and the message names the
class and property. The following are rejected:

- a class that doesn't exist, has no `#[Table]`, or is abstract;
- an entity without any `#[Id]`;
- a static, untyped, or union/intersection-typed mapped property (unless
  `#[Column(type:)]` names a converter);
- two properties mapped to the same column;
- a generated `#[Id]` inside a composite key;
- more than one `#[Version]`, a non-`int` or nullable `#[Version]`, or
  `#[Id]` and `#[Version]` on the same property;
- a `readonly` generated `#[Id]` or `#[Version]`;
- a property that is both a relation and a column, or has more than one
  relation attribute;
- a relation property not typed as `Collection` (to-many, not nullable) or
  the target class (to-one), or declaring a default value;
- relation key lists of different lengths, including against the `Via`
  columns;
- relation keys, `where` columns or `orderBy` columns that are not mapped on
  the entity they refer to;
- an `orderBy` direction other than `ASC` or `DESC`, on the relation or on
  `Via`;
- a `Via` `orderBy` column that is not a plain column name (letters, digits
  and underscores, not starting with a digit), such as `'user_tag.sequence'`
  or `'sequence DESC'`;
- a relation attribute the factory doesn't recognise.

Join-table columns are not checked against the database, because the join
table has no entity. A misspelt key or `Via` `orderBy` column fails when the
relation is first loaded.

## Reading metadata

`Contenir\Db\Model\Metadata\AttributeMetadataFactory` turns the attributes
into an immutable `EntityMetadata`:

```php
$factory  = new AttributeMetadataFactory();
$metadata = $factory->getMetadataFor(User::class);

$metadata->table;                         // 'users'
$metadata->getTableIdentifier();          // PhpDb\Sql\TableIdentifier
$metadata->getColumnNames();              // ['id', 'email', 'created_at', 'version']
$metadata->getField('createdAt');         // FieldMetadata: columnName 'created_at', type, role
$metadata->getFieldForColumn('user_id');  // lookup by column
$metadata->getIdentifierColumns();        // ['id']
$metadata->generatedIdentifier;           // FieldMetadata|null
$metadata->version;                       // FieldMetadata|null
$metadata->getRelation('orders');         // RelationMetadata: kind, targetClass, keys, criteria
```

Metadata is memoised per factory instance. Reflection happens once per
class per process. To reuse metadata across processes, see
[metadata caching](metadata-caching.md).
