<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Relation;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\RelationException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\RelationMetadata;

/**
 * Prepares a managed entity's relation properties: collections become
 * lazy {@see Collection}s, single relations are unset so the
 * {@see LazyRelationsTrait} trait (if used) can load them on first read, and
 * the entity is registered with the {@see RelationResolver}.
 *
 * @internal
 */
final class RelationInitializer
{
    private ?RelationLoader $loader = null;

    public function __construct(
        private readonly PropertyAccessor $accessor,
    ) {}

    /**
     * Assign preloaded targets to an owner's relation property.
     *
     * @param list<object> $targets
     *
     * @throws HydrationException
     * @throws RelationException When a non-nullable single relation has no target.
     */
    public function assign(object $entity, RelationMetadata $relation, array $targets): void
    {
        $this->accessor->set($entity, $relation->name, $this->value($entity, $relation, $targets));
    }

    /**
     * Completes construction: the loader depends on the entity loader,
     * which depends on this initializer.
     */
    public function attach(RelationLoader $loader): void
    {
        $this->loader = $loader;
    }

    /**
     * Fill relation properties that are still uninitialised. Values the
     * caller already assigned are kept.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     */
    public function initialize(EntityMetadata $metadata, object $entity): void
    {
        if ([] !== $metadata->relations) {
            foreach ($metadata->relations as $relation) {
                if ($this->accessor->isInitialized($entity, $relation->name)) {
                    continue;
                }

                $this->prepare($metadata, $relation, $entity);
            }

            RelationResolver::register($entity, new RelationBinding($this, $metadata));
        }
    }

    /**
     * Discard loaded relations (e.g. after a refresh) and prepare them
     * again.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     */
    public function reset(EntityMetadata $metadata, object $entity): void
    {
        foreach ($metadata->relations as $relation) {
            $this->accessor->reset($entity, $relation->name);
        }

        $this->initialize($metadata, $entity);
    }

    /**
     * @param EntityMetadata<object> $metadata
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException
     * @throws RelationException
     * @throws TypeConversionException
     */
    public function resolve(EntityMetadata $metadata, object $entity, string $name): mixed
    {
        $relation = $metadata->relations[$name] ?? throw RelationException::undefinedProperty($entity::class, $name);
        $value    = $this->value($entity, $relation, $this->targets($metadata, $relation, $entity));
        $this->accessor->set($entity, $name, $value);

        return $value;
    }

    /**
     * A collection becomes lazy; a single relation is unset so the trait's
     * __get() is reached on first read.
     *
     * @param EntityMetadata<object> $metadata
     *
     * @throws HydrationException
     */
    private function prepare(EntityMetadata $metadata, RelationMetadata $relation, object $entity): void
    {
        if (! $relation->isCollection()) {
            $this->accessor->reset($entity, $relation->name);

            return;
        }

        $this->accessor->set($entity, $relation->name, Collection::lazy(
            /**
             * @return list<object>
             *
             * @throws HydrationException
             * @throws IdentityConflictException
             * @throws MappingException
             * @throws PersistenceException
             * @throws RelationException
             * @throws TypeConversionException
             */
            fn(): array => $this->targets($metadata, $relation, $entity),
        ));
    }

    /**
     * @param EntityMetadata<object> $metadata
     *
     * @return list<object>
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException
     * @throws RelationException
     * @throws TypeConversionException
     */
    private function targets(EntityMetadata $metadata, RelationMetadata $relation, object $entity): array
    {
        $loader = $this->loader ?? throw RelationException::notLoaded($entity::class, $relation->name);
        $key    = $loader->keyOf($metadata, $relation, $entity);

        return null === $key ? [] : $loader->load($metadata, $relation, [$entity])[$key] ?? [];
    }

    /**
     * @param list<object> $targets
     *
     * @return Collection<object>|object|null
     *
     * @throws HydrationException
     * @throws RelationException
     */
    private function value(object $entity, RelationMetadata $relation, array $targets): ?object
    {
        if ($relation->isCollection()) {
            return Collection::of($targets);
        }

        $target = $targets[0] ?? null;
        if (null === $target && ! $this->accessor->allowsNull($entity, $relation->name)) {
            throw RelationException::missingRelated($entity::class, $relation->name);
        }

        return $target;
    }
}
