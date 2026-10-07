<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Hydrator;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use WeakMap;

use function array_key_exists;

/**
 * Remembers the database values of each loaded or saved entity so writes
 * can be limited to the columns that actually changed.
 *
 * Snapshots are taken in database form (after type conversion), so two
 * DateTime instances for the same moment, or the same enum case, compare
 * equal. Entities are held weakly: tracking never keeps an entity alive.
 *
 * @internal
 */
final class ChangeTracker
{
    /**
     * @var WeakMap<object, array<string, int|float|string|bool|null>>
     */
    private WeakMap $snapshots;

    public function __construct(
        private readonly EntityHydrator $hydrator,
    ) {
        $this->snapshots = new WeakMap();
    }

    /**
     * Columns whose values differ from the snapshot, as database values.
     * An untracked entity has no snapshot, so every initialised column is
     * returned.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @return array<string, int|float|string|bool|null>
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function changes(EntityMetadata $metadata, object $entity): array
    {
        $current  = $this->hydrator->extract($metadata, $entity);
        $snapshot = $this->snapshots[$entity] ?? null;
        if (null === $snapshot) {
            return $current;
        }

        $changes = [];
        foreach ($current as $column => $value) {
            if (array_key_exists($column, $snapshot) && $snapshot[$column] === $value) {
                continue;
            }

            $changes[$column] = $value;
        }

        return $changes;
    }

    public function clear(): void
    {
        $this->snapshots = new WeakMap();
    }

    public function forget(object $entity): void
    {
        unset($this->snapshots[$entity]);
    }

    public function isTracked(object $entity): bool
    {
        return $this->snapshots->offsetExists($entity);
    }

    /**
     * Put back a snapshot previously read with {@see self::snapshotOf()},
     * or stop tracking when it was null.
     *
     * @param array<string, int|float|string|bool|null>|null $snapshot
     */
    public function restore(object $entity, ?array $snapshot): void
    {
        if (null !== $snapshot) {
            $this->snapshots[$entity] = $snapshot;

            return;
        }

        $this->forget($entity);
    }

    /**
     * Record the entity's current values as its persisted state.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function snapshot(EntityMetadata $metadata, object $entity): void
    {
        $this->snapshots[$entity] = $this->hydrator->extract($metadata, $entity);
    }

    /**
     * The persisted value of a column as last snapshotted, used for
     * optimistic-lock and primary-key predicates.
     *
     * @return array<string, int|float|string|bool|null>|null
     */
    public function snapshotOf(object $entity): ?array
    {
        return $this->snapshots[$entity] ?? null;
    }
}
