<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Identity;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Hydrator\ChangeTracker;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Metadata\EntityMetadata;

/**
 * Turns rows into managed entities: the identity map is consulted first,
 * and only rows for identities not yet in memory are hydrated, registered
 * and snapshotted.
 *
 * An entity already in the map is returned as-is; its in-memory state
 * (including unsaved edits) wins over the row. Use an explicit refresh to
 * pick up the stored values.
 *
 * @internal
 */
final readonly class EntityLoader
{
    public function __construct(
        private EntityHydrator $hydrator,
        private ChangeTracker $tracker,
        private IdentityMap $identityMap,
        private IdentifierResolver $identifiers,
    ) {}

    /**
     * @template T of object
     *
     * @param EntityMetadata<T>    $metadata
     * @param array<string, mixed> $row
     *
     * @return T
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws TypeConversionException
     */
    public function load(EntityMetadata $metadata, array $row): object
    {
        $identifier = $this->identifiers->fromRow($metadata, $row);
        $existing   = $this->identityMap->get($metadata->className, $identifier);
        if (null !== $existing) {
            return $existing;
        }

        $entity = $this->hydrator->hydrate($metadata, $row);
        $this->identityMap->add($metadata->className, $identifier, $entity);
        $this->tracker->snapshot($metadata, $entity);

        return $entity;
    }
}
