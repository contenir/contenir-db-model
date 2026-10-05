# Entity lifecycle: hydration and change tracking

This page explains what happens to an entity object between the database
and your code. It covers the rules an entity class has to live with. The
classes involved (`EntityHydrator`, `ChangeTracker`, `PropertyAccessor`)
are internal; repositories and the entity manager drive them for you.

## Loading: how an entity is built from a row

1. **The constructor is not called.** Entities are created with
   `ReflectionClass::newInstanceWithoutConstructor()`, so a constructor can
   enforce invariants for code that creates *new* entities without getting
   in the way of loading existing ones. Don't rely on the constructor to set
   up state that loaded entities need.
2. **Defaults still apply.** Property defaults declared in the class (such
   as `public int $version = 1;`) are present before any column is
   assigned.
3. **Each mapped column in the row is converted and assigned** to its
   property through the [type converters](types.md). Columns missing from
   the row (a partial select) leave the property at its default, or
   uninitialised if it has none. Columns in the row that aren't mapped are
   ignored.
4. **Any visibility works.** Mapped properties may be `private`,
   `protected` or `public`, including `readonly` ones and those inherited
   from a parent class.
5. **Relations are prepared, not loaded.** To-many properties get a lazy
   `Collection`, and to-one properties are left unset for lazy loading; see
   [relations](relations.md).

A database `NULL` for a non-nullable property throws
`TypeConversionException`. Declare columns that can be NULL as `?type`.

## Already-loaded rows

Before a row is hydrated, the [identity map](identity-map.md) is checked.
If the entity for that primary key is already in memory, that object is
returned as-is, and the steps above are skipped.

## Refreshing an already-loaded entity

Refreshing (for example, `save()` with refresh, or an explicit reload)
writes the new row values over the existing object rather than creating a
new one:

- Mutable properties are overwritten.
- A `readonly` property that is still uninitialised is set.
- A `readonly` property that is already set is left alone if the stored
  value is the same. If the stored value differs, `HydrationException` is
  thrown, because a readonly property cannot change once set.

## Change tracking

When an entity is loaded or saved, a **snapshot** of its column values is
recorded. A later save compares the entity against that snapshot and writes
only the columns that differ.

- **Snapshots hold database values.** Values are compared after
  conversion, so replacing a `DateTimeImmutable` with another instance for
  the same moment, or re-assigning the same enum case, does not count as a
  change. Objects never need to be the same instance to compare equal.
- **Uninitialised properties are skipped.** A property that has never been
  assigned is not part of the snapshot or the change set. If it is assigned
  after the snapshot, it counts as a change.
- **New entities have no snapshot.** Every initialised column is treated as
  changed, which makes it an insert.
- **Tracking is weak.** Snapshots are held in a `WeakMap`, so they never
  keep an entity in memory. When your code drops its last reference, the
  snapshot goes too.
- **JSON columns compare as encoded text.** Re-ordering the keys of an
  array stored in a `json` column counts as a change.

## Writing an entity class that works well

- Give every property that can be NULL in the database a nullable type.
- A generated `#[Id]` is easiest as `public ?int $id = null;`, which lets
  new entities read `null` before they are saved.
- Use `readonly` for values that never change after creation, such as
  natural keys. Keep generated ids and `#[Version]` mutable; the mapping
  rejects them as `readonly`.
- Constructors are welcome for creating new entities. They are skipped on
  load.

## Errors

| Exception | Raised when |
| --- | --- |
| `TypeConversionException` | A column value cannot be converted, or NULL meets a non-nullable property. |
| `HydrationException` | The entity class cannot be instantiated, a mapped property cannot be accessed, or a refresh would change a `readonly` property that is already set. |

Both extend `Contenir\Db\Model\Exception\RuntimeException` and implement
`ExceptionInterface`.
