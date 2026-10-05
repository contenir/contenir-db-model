# Persisting entities

`Contenir\Db\Model\EntityManager` writes entities to the database and owns
the [identity map](identity-map.md) and [change tracking](entity-lifecycle.md)
for one unit of work.

```php
use Contenir\Db\Model\EntityManager;

$em = new EntityManager(
    $adapter,                 // PhpDb\Adapter\AdapterInterface
    $metadataFactory,         // optional; defaults to AttributeMetadataFactory (wrap it in CachedMetadataFactory for production)
    $typeRegistry,            // optional; defaults to TypeRegistry::withDefaults()
);
```

Create **one entity manager per request** (or per job in a worker), and
call `clear()` between jobs in long-running processes.

## API

| Method | Does |
| --- | --- |
| `save($entity)` | Insert a new entity, or update the changed columns of a managed one |
| `saveAndRefresh($entity)` | `save()`, then reload the stored row, in one transaction |
| `delete($entity)` | Delete the row and stop managing the entity |
| `refresh($entity)` | Overwrite the entity with its stored values, discarding unsaved edits |
| `transactional(callable)` | Run work in a transaction and return its result |
| `contains($entity)` | Whether the entity is managed |
| `clear()` | Stop managing every entity |

## `save()`: insert or update

- **Insert** happens when the entity is **not managed**: you created it
  with `new`, it was deleted, or the manager was cleared.
- **Update** happens when the entity is **managed**: it was loaded or
  saved through this manager.

This replaces 1.x's "is the primary key null?" guess. An entity with a
natural key that you create with `new` is always inserted, and an insert
of an existing key fails with the database's duplicate-key error.

### Inserts

- Every **initialised** mapped property is written. Uninitialised ones are
  left out, so database defaults apply to them.
- A **generated** `#[Id]` that is null or uninitialised is left out of the
  INSERT. The value the database generates is converted and written back
  onto the entity. If the driver reports no generated value, the insert
  throws `PersistenceException`.
- An explicitly set value for a generated key is inserted as-is.
- A non-generated key that is missing or null is rejected **before any SQL
  is sent**, with `PersistenceException` ("Cannot insert …").
- Afterwards the entity is managed and snapshotted.

> **PostgreSQL:** generated values come from phpdb's driver result. If
> your platform needs a sequence name to report the last insert id,
> configure that on the phpdb side. Without a value, the insert fails
> loudly rather than leaving the id unset.

### Updates

- Only the columns that **changed since the last load or save** are
  written. If nothing changed, no SQL is issued at all.
- The WHERE clause uses the primary key **as it was loaded**. You can
  therefore change a primary key property, and the update moves the row.
  The identity map is re-keyed afterwards.
- Setting a primary key property to null is rejected before any SQL is
  sent.

### Optimistic locking

With a `#[Version]` property, updates are guarded:

```sql
UPDATE widgets SET name = ?, version = 3 WHERE id = ? AND version = 2
```

- On success, the new version is written back onto the entity.
- If no row matches, because someone else updated or deleted it first,
  `StaleEntityException` is thrown. Reload with `refresh()`, re-apply your
  change, and save again.
- Deletes of a loaded versioned entity are guarded the same way.

Because the version always changes, the "one row affected" check is
reliable even on MySQL, which otherwise reports zero affected rows for
updates that don't change any values. Without `#[Version]`, an update that
matches no row is not treated as an error.

## `delete()`

Deletes by the primary key the database holds: the loaded key for a
managed entity, or the entity's current key for an unmanaged one. An
entity with no key throws `PersistenceException` ("Cannot delete …").
Afterwards the entity is no longer managed. Saving it again would insert
it.

## `refresh()` and `saveAndRefresh()`

`refresh()` re-reads the row and overwrites the entity's mapped properties.
Unsaved edits are lost. It throws `PersistenceException` if the entity has
no key or its row no longer exists. Readonly properties follow the
[refresh rules](entity-lifecycle.md#refreshing-an-already-loaded-entity).

Use `saveAndRefresh()` when the database changes values on write, through
column defaults, triggers or computed columns. The write and the reload
share a transaction.

## Transactions

```php
$orderId = $em->transactional(function () use ($em, $order, $stock): int {
    $em->save($order);
    $em->save($stock);

    return $order->id;
});
```

- The callable's return value is returned.
- **Re-entrant.** A call made while a transaction is already open (yours
  or an outer `transactional()`) joins it. Only the outermost call commits
  or rolls back.
- **Any exception rolls back and is re-thrown.**
- **In-memory tracking is rolled back too.** For every entity written
  inside the failed transaction:
  - an **inserted** entity becomes new again. Its generated id returns to
    its previous value (null, or uninitialised), and it is no longer
    managed;
  - an **updated** entity regains its previous snapshot and version, so
    its edits count as unsaved again;
  - a **deleted** entity is managed again.

  Your property edits are kept, so retrying the same saves after a rollback
  works. If the driver has already ended the transaction itself (for
  example after a MySQL deadlock), no second rollback is attempted.

Writes made outside `transactional()` are each a single auto-committed
statement.

## Errors

| Exception | Raised when |
| --- | --- |
| `StaleEntityException` | A `#[Version]`-guarded update or delete matched no row |
| `PersistenceException` | Missing primary key, row not found on refresh, no generated value, or no driver result |
| `IdentityConflictException` | A second instance with the same primary key as a managed entity is registered |
| `TypeConversionException`, `HydrationException` | Value conversion or property access failed (see [types](types.md) and the [entity lifecycle](entity-lifecycle.md)) |
| `MappingException` | The entity class is not validly mapped |

Database errors from the driver, such as constraint violations or
duplicate keys, propagate unchanged.
