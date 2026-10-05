<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Hydrator\ChangeTracker;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
use Contenir\Db\Model\Identity\IdentifierResolver;
use Contenir\Db\Model\Identity\IdentityMap;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Type\TypeRegistry;

use function array_flip;
use function array_intersect_key;

/**
 * The in-memory state of one entity manager: which entities are managed,
 * under which identifiers, and what their last persisted values were.
 *
 * @internal
 */
final readonly class Session
{
    public function __construct(
        public EntityHydrator $hydrator,
        public ChangeTracker $tracker,
        public IdentityMap $identityMap,
        public IdentifierResolver $identifiers,
        public PropertyAccessor $accessor,
    ) {}

    public static function create(TypeRegistry $types): self
    {
        $accessor = new PropertyAccessor();
        $hydrator = new EntityHydrator($types, $accessor);

        return new self(
            $hydrator,
            new ChangeTracker($hydrator),
            new IdentityMap(),
            new IdentifierResolver($types, $accessor),
            $accessor,
        );
    }

    public function clear(): void
    {
        $this->identityMap->clear();
        $this->tracker->clear();
    }

    public function detach(object $entity): void
    {
        $this->identityMap->remove($entity);
        $this->tracker->forget($entity);
    }

    /**
     * The identifier the database currently holds for $entity: taken from
     * its snapshot when managed (so a changed primary key still finds the
     * stored row), otherwise from its properties.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @return array<string, int|float|string|bool|null>|null
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function persistedIdentifier(EntityMetadata $metadata, object $entity): ?array
    {
        $snapshot = $this->tracker->snapshotOf($entity);
        if (null === $snapshot) {
            return $this->identifiers->fromEntity($metadata, $entity);
        }

        $identifier = array_intersect_key($snapshot, array_flip($metadata->getIdentifierColumns()));

        return [] === $identifier ? null : $identifier;
    }

    /**
     * Mark $entity as managed under $identifier with its current values as
     * the persisted state.
     *
     * @template T of object
     *
     * @param EntityMetadata<T>                    $metadata
     * @param T                                    $entity
     * @param array<string, int|float|string|bool> $identifier
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws TypeConversionException
     */
    public function register(EntityMetadata $metadata, object $entity, array $identifier): void
    {
        $this->identityMap->add($metadata->className, $identifier, $entity);
        $this->tracker->snapshot($metadata, $entity);
    }

    /**
     * The identifier the entity's properties hold now.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @return array<string, int|float|string|bool>
     *
     * @throws HydrationException
     * @throws PersistenceException When the entity has no complete primary key.
     * @throws TypeConversionException
     */
    public function requireIdentifier(string $operation, EntityMetadata $metadata, object $entity): array
    {
        return (
            $this->identifiers->fromEntity($metadata, $entity)
                ?? throw PersistenceException::missingIdentifier($operation, $metadata->className)
        );
    }
}
