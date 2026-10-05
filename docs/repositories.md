# Repositories and finders

`Contenir\Db\Model\Repository` loads entities of one class. Every entity
it returns is **managed** by the owning [`EntityManager`](persistence.md):
it can be saved directly, and rows that are already loaded come back as the
same instances (see the [identity map](identity-map.md)).

```php
$users = $em->getRepository(User::class);   // Repository<User>, one instance per class

$user   = $users->find(1);
$active = $users->findBy(['status' => Status::Active], ['createdAt' => 'DESC'], limit: 20);
```

## Finders

| Method | Returns |
| --- | --- |
| `find($id, ?Select $select = null)` | The entity with that primary key, or `null` |
| `findOneBy(array $criteria, array $orderBy = [], ?Select $select = null)` | The first match, or `null` |
| `findBy(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, ?Select $select = null)` | `list` of matches; no arguments means every row |
| `count(array $criteria = [])` | Number of matching rows |
| `stream(array $criteria = [], array $orderBy = [], ?Select $select = null)` | `Generator` yielding matches one at a time |
| `createSelect()` | A `PhpDb\Sql\Select` over the entity's table, for custom queries |
| `fetch(Select $select)` / `fetchOne(Select $select)` | Entities from a custom select |

### `find()`

- **Single-column key:** pass the value, as `find(5)` or `find('5')`. Both
  work, because the value goes through the key's
  [type converter](types.md).
- **Composite key:** pass an array keyed by **property** name, containing
  exactly the key properties. For example,
  `find(['groupId' => 1, 'userId' => 2])`.
- **Already loaded:** if the entity is already managed, it is returned
  **without running a query**, unless a `$select` is passed (see
  [finders on a custom select](#finders-on-a-custom-select)).
- **Bad keys:** a malformed key (a scalar for a composite key, or missing
  or extra properties) throws `QueryException`.

### Criteria

Criteria are an array keyed by **property name**, not column name:

```php
$orders->findBy([
    'status'   => OrderStatus::Shipped,           // equality
    'userId'   => [1, 2, 3],                      // a list becomes IN (...)
    'placedAt' => new DateTimeImmutable('today'), // objects go through the type converters
    'note'     => null,                           // null becomes IS NULL
]);
```

- Conditions are combined with `AND`.
- Values are converted to database form by the column's type converter.
  So an enum case and its backing value (`OrderStatus::Shipped` or
  `'shipped'`) both work, as do `5` and `'5'`.
- **Unknown property names throw `QueryException`.** Criteria keys are
  checked against the mapping, so caller-supplied keys, such as filter
  names from a query string, can never inject SQL identifiers.
- For anything beyond `AND`ed equality, `IN` and `IS NULL`, use a
  [custom select](#custom-queries).

### Ordering

`$orderBy` is `['property' => 'ASC'|'DESC']`. The direction is
case-insensitive. Unknown properties or directions throw `QueryException`.

## Streaming large result sets

`stream()` executes the query straight away (so errors surface at the
call), then yields entities as rows are read, without building an array:

```php
foreach ($orders->stream(['status' => OrderStatus::Pending]) as $i => $order) {
    $exporter->write($order);

    if ($i % 1000 === 999) {
        $em->clear();   // the identity map still holds every streamed entity
    }
}
```

Streamed entities are still managed and held by the identity map. For
very large scans, clear the entity manager periodically, or memory grows
with every row.

## Custom queries

Use `createSelect()` for joins, ranges, `LIKE`, sub-queries and anything
else phpdb's `Select` can express. Then hydrate the result with `fetch()`
or `fetchOne()`:

```php
$select = $users->createSelect();
$select->where->like('email', '%@example.com')
              ->greaterThan('created_at', '2024-01-01');
$select->order('created_at DESC');

$recent = $users->fetch($select);
```

- `createSelect()` lists every mapped column, qualified by table. In raw
  `Select` calls, conditions use **column** names.
- The select must return the **primary-key columns**, or a
  `HydrationException` is thrown.
- Extra selected columns that aren't mapped are ignored.

## Finders on a custom select

`find()`, `findOneBy()`, `findBy()` and `stream()` take an optional
`Select` as the base query. Criteria, ordering, limit and offset are added
on top of it, so joins and complex predicates combine with property-keyed
criteria:

```php
$select = $users->createSelect()
    ->join('orders', 'orders.user_id = users.id', [])
    ->where(['orders.status' => 'shipped']);

$users->findBy(['country' => 'AU'], ['name' => 'ASC'], limit: 20, select: $select);
$users->findOneBy(['email' => $email], select: $select);
$users->find(42, $select);   // null unless user 42 also has a shipped order
foreach ($users->stream([], ['id' => 'ASC'], $select) as $user) { /* … */ }
```

- **The select is cloned.** Your `Select` object is never modified, so it
  can be reused.
- **Columns are qualified.** Criteria and ordering columns are qualified
  with the entity's table (`users.country`), so they stay unambiguous when
  the select joins tables with columns of the same name. Write your own
  conditions on the select with qualified column names too.
- **`find()` always queries when given a select,** even if the entity is
  already loaded, because the select may add conditions the entity must
  meet. When the row matches, the managed instance is still the one
  returned.
- **Selecting columns.** Start from `createSelect()`, which lists the
  entity's columns, and pass `[]` as the column list of any join, so
  joined columns don't replace the entity's. The primary-key columns must
  stay selected. A join that matches several rows per entity returns that
  entity several times (the same instance each time); add a
  `->quantifier('DISTINCT')` or group if that matters.

## Custom repositories

Extend `Repository` to give queries a name and keep them in one place:

```php
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Repository;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, User::class);
    }

    /**
     * @return list<User>
     */
    public function findByEmailDomain(string $domain): array
    {
        $select = $this->createSelect();
        $select->where->like('email', '%@' . $domain);

        return $this->fetch($select);
    }
}
```

Construct custom repositories directly, or register them with your
container, passing the request's `EntityManager`. They can take any other
constructor dependencies they need. Inside a subclass, `$this->em` is the
entity manager and `$this->metadata` the entity's mapping.

`$em->getRepository()` always returns the generic `Repository` for a
class; it does not know about subclasses.

## Errors

| Exception | Raised when |
| --- | --- |
| `QueryException` | Unknown criteria or order property, invalid direction, malformed `find()` key |
| `TypeConversionException` | A criteria value cannot be converted for its column |
| `HydrationException` | A selected row lacks primary-key columns, or a value cannot be hydrated |
| `PersistenceException` | The driver returned no result |
