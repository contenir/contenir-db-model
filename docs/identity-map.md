# Identity map

Within one entity manager, each database row is represented by **at most
one object**. Loading the same row twice, through the same query or two
different ones, returns the same instance:

```php
$a = $users->findOne(['id' => 1]);
$b = $users->findOneBy('email', 'a@example.com');   // same row

$a === $b;   // true
```

So an edit made through one reference is visible through every other
reference, and a save cannot overwrite changes made through a second copy.
(The repository API shown here arrives with the repository docs.)

## How rows are matched

Entities are keyed by **entity class plus primary-key values**. Key values
are normalised to their database form through the
[type converters](types.md). That means a driver returning `'5'` and a
property holding `5` produce the same key, and composite keys work.

Every query that loads entities must therefore **select the primary-key
columns**. A row without a non-null value for each `#[Id]` column throws
`HydrationException`:

```
Cannot load entity "App\Entity\User": the row lacks a non-null value for
identifier column(s) [id]; include the primary key in the select
```

This is deliberate. An entity without its key could not be tracked, and
saving it later would insert a duplicate.

## In-memory state wins

When a query returns a row for an entity that is already loaded, the
**existing object is returned unchanged**. The new row values are
discarded. Unsaved edits are therefore never silently lost when another
query happens to load the same row.

To pick up values that changed in the database, refresh the entity
explicitly. That is covered with the entity manager.

## New entities

An entity you create with `new` is not in the identity map until it is
saved. Once an insert assigns its primary key (including a generated id),
it is registered, and later loads of that row return it.

Creating a *second* object for a primary key that is already loaded, then
saving it, throws `IdentityConflictException`. Load the existing entity and
change it instead.

## Memory and long-running processes

The identity map holds **strong references**. That is what guarantees one
object per row, but it also means every loaded entity stays in memory until
the map is cleared.

- **PHP-FPM / per-request:** nothing to do. The map dies with the request.
- **Workers, queue consumers, daemons, long CLI imports:** clear the map
  between units of work. Otherwise memory grows with every row loaded.

```php
foreach ($jobs as $job) {
    $handler->handle($job);
    $entityManager->clear();   // forget all managed entities and snapshots
}
```

After a clear, previously loaded objects are no longer managed. Loading
their rows again creates fresh instances.

Change-tracking snapshots, by contrast, are held weakly and never keep an
entity alive on their own (see [entity lifecycle](entity-lifecycle.md)).
