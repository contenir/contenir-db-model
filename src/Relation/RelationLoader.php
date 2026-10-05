<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Relation;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Identity\EntityLoader;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Contenir\Db\Model\Metadata\RelationMetadata;
use Contenir\Db\Model\Persistence\RowFetcher;

use function array_values;
use function serialize;

/**
 * Loads a relation's targets for one or many owners in a single query and
 * groups them by owner key. Targets go through the identity map, so
 * entities already in memory are reused.
 *
 * @internal
 */
final readonly class RelationLoader
{
    public function __construct(
        private MetadataFactoryInterface $metadata,
        private RowFetcher $rows,
        private EntityLoader $loader,
        private EntityHydrator $hydrator,
        private RowGroupKey $groupKeys,
    ) {}

    /**
     * Grouping key for an owner, or null when any of its local key values
     * is null (such an owner has no related rows).
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $owner
     * @param T                 $entity
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function keyOf(EntityMetadata $owner, RelationMetadata $relation, object $entity): ?string
    {
        $tuple = $this->tupleOf($owner, $relation, $entity);

        return null === $tuple ? null : serialize($tuple);
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $owner
     * @param list<T>           $entities
     *
     * @return array<string, list<object>> targets keyed by {@see self::keyOf()}
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function load(EntityMetadata $owner, RelationMetadata $relation, array $entities): array
    {
        $tuples = [];
        foreach ($entities as $entity) {
            $tuple = $this->tupleOf($owner, $relation, $entity);
            if (null !== $tuple) {
                $tuples[serialize($tuple)] = $tuple;
            }
        }

        if ([] === $tuples) {
            return [];
        }

        $target = $this->metadata->getMetadataFor($relation->targetClass);
        $select = RelationSelect::build($this->rows->select($target), $target, $relation, array_values($tuples));

        $groups = [];
        foreach ($this->rows->rows($select) as $row) {
            $groups[$this->groupKeys->of($owner, $target, $relation, $row)][] = $this->loader->load($target, $row);
        }

        return $groups;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $owner
     * @param T                 $entity
     *
     * @return list<int|float|string|bool>|null
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    private function tupleOf(EntityMetadata $owner, RelationMetadata $relation, object $entity): ?array
    {
        $values = $this->hydrator->extract($owner, $entity);
        $tuple  = [];
        foreach ($relation->keys->localColumns as $column) {
            $value = $values[$column] ?? null;
            if (null === $value) {
                return null;
            }

            $tuple[] = $value;
        }

        return $tuple;
    }
}
