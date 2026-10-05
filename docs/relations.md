# Relations

Relations are declared with the attributes described in
[mapping](mapping.md#relation-attributes) and are **read-only**. Loading an
entity gives you its related entities. Saving an entity never writes its
relations, so you change a relation by saving the related entity with the
right foreign key.

```php
use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Relation\LazyRelationsTrait;

#[Table('users')]
final class User
{
    use LazyRelationsTrait;                       // lazy HasOne / BelongsTo

    #[Id(generated: true)]
    public ?int $id = null;

    /** @var Collection<Order> */
    #[HasMany(Order::class, foreignKey: 'user_id', orderBy: ['placed_at' => 'DESC'])]
    public Collection $orders;                    // always lazy

    #[HasOne(Profile::class, foreignKey: 'user_id')]
    public ?Profile $profile;                     // nullable: a user may have no profile

    /** @var Collection<Tag> */
    #[ManyToMany(Tag::class, via: new Via('user_tag', foreignKey: 'user_id', relatedKey: 'tag_id'))]
    public Collection $tags;
}
```

## Declaring relation properties

The mapping checks relation properties when metadata is built. A violation
throws `MappingException`.

| Relation | Property type | Default value |
| --- | --- | --- |
| `HasMany`, `ManyToMany` | `Collection` (not nullable) | none |
| `HasOne`, `BelongsTo` | the target class; make it nullable (`?Profile`) when the related row may not exist | none |

Relation properties must **not** have a default value. An unloaded relation
stays uninitialised, so reading it either loads it (see below) or fails
loudly. It never silently reads as "empty". `public ?Profile $profile = null;`
would read `null` before loading and look like "no profile", so it is
rejected.

## To-many: `Collection`

Every managed entity's `HasMany` and `ManyToMany` properties hold a lazy
`Contenir\Db\Model\Collection`:

```php
$user = $users->find(1);

$user->orders->isLoaded();   // false: no query yet
foreach ($user->orders as $order) { /* one query, on first use */ }
count($user->orders);        // no further query
$user->orders->first();      // first item or null
$user->orders->isEmpty();
$user->orders->toArray();    // list<Order>
```

- The collection queries **once**, on first use (iteration, `count()`,
  `toArray()`, `first()` or `isEmpty()`), and keeps the result.
- The relation's `where` criteria and `orderBy` from the mapping are
  applied.
- Collections are read-only. `Collection::of([...])` builds an
  already-loaded collection, which you can assign to a new entity before
  saving it; an assigned collection is kept.
- An entity inserted with `save()` gets lazy collections too, so
  `$newUser->orders` works straight after saving.

## To-one: `HasOne` and `BelongsTo`

Single relations are loaded lazily **only if the entity uses
`LazyRelationsTrait`**:

```php
$order = $orders->find(3);
$order->user;            // queries for the user on first read, then it's a normal property
isset($user->profile);   // loads; false when there is no profile
```

- The trait adds `__get()` and `__isset()`. Managed entities have their
  single-relation properties **unset**, so the first read reaches
  `__get()`, which loads the target and assigns it to the property. Later
  reads are plain property reads.
- If the entity already has its own `__get()`, don't use the trait.
  Preload single relations instead.
- **Without the trait**, single relations must be filled with
  [`preload()`](#preloading-avoiding-n1). Reading one that wasn't preloaded
  throws PHP's "must not be accessed before initialization" `Error`.
- A **non-nullable** single relation whose related row is missing (a
  dangling foreign key) throws `RelationException` on load. Declare the
  property nullable if the row may legitimately be absent.

### Entities that can't lazy-load

Lazy loading needs an entity that the entity manager prepared, either by
loading it or by saving it:

- **An entity you built with `new` and haven't saved:** its single
  relations were never assigned, so reading one throws PHP's
  "must not be accessed before initialization" `Error`.
- **A `clone` of a managed entity:** it copies the unset properties but is
  not managed, so reading one throws `RelationException` ("Relation … is not
  loaded"). A clone never queries through the original's manager.

## Preloading: avoiding N+1

Iterating 100 users and touching `$user->orders` runs 100 queries.
`Repository::preload()` loads a relation for many entities in **one query
per relation**:

```php
$list = $users->findBy(['active' => true]);
$users->preload($list, 'orders', 'profile', 'tags');

foreach ($list as $user) {
    foreach ($user->orders as $order) { /* no queries */ }
}
```

- **Nested paths:** `'orders.user'` loads the orders, then the users of
  all those orders, one query per level. Paths sharing a prefix share its
  query.
- **Every relation kind** works: `HasMany`, `HasOne`, `BelongsTo` and
  `ManyToMany` (via a join on the join table), each with its `where` and
  `orderBy` mapping criteria.
- **Composite keys** work. Single-column keys use `IN (…)`; composite keys
  use `(a = ? AND b = ?) OR …`, which every platform supports.
- **Owners with a null key** (for example unsaved entities) get an empty
  collection or `null`, with no query.
- **Preloading replaces** any relation already loaded on those entities,
  with fresh results.
- **It fills single relations on entities without `LazyRelationsTrait`.**
- All entities passed must be of the repository's class, or
  `RelationException` is thrown. An undeclared relation name throws
  `MappingException`.

Related entities go through the [identity map](identity-map.md). If a
target is already in memory, that same instance is used.

## Staleness and refreshing

Loaded relations are a snapshot taken when they were loaded. If related
rows change later, including through `save()` on a related entity, an
already loaded collection does not change.

- `$em->refresh($entity)` reloads the entity's columns **and resets its
  relations**, so they load again on next use.
- `preload()` replaces the relations it loads.

## Saving and relations

Relations are never written:

```php
$order = new Order();
$order->userId = $user->id;   // set the foreign key column…
$em->save($order);            // …and save the related entity

$user->orders;                // already loaded? still the old list (see Staleness)
```

Cascading saves and deletes across relations are not supported.

## Errors

| Exception | Raised when |
| --- | --- |
| `MappingException` | A relation property has the wrong type or a default value; `preload()` names an undeclared relation |
| `RelationException` | A non-nullable single relation has no row; the entity is not managed (for example a clone); `preload()` got entities of another class; an undefined property is read through the trait |
| PHP `Error` ("must not be accessed before initialization") | A single relation is read without the trait and without preloading, or on an unsaved `new` entity |
