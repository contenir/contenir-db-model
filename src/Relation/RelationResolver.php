<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Relation;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\RelationException;
use Contenir\Db\Model\Exception\TypeConversionException;
use WeakMap;

/**
 * Process-wide lookup from managed entity to the {@see RelationBinding}
 * that loads its relations, consulted by {@see LazyRelationsTrait}. Held
 * in a WeakMap outside the entity so entities stay serialisable and are
 * never kept alive by the registry.
 *
 * @internal
 */
final class RelationResolver
{
    /**
     * @var WeakMap<object, RelationBinding>|null
     */
    private static ?WeakMap $bindings = null;

    /**
     * Whether the relation is loadable and non-null. A relation that cannot
     * be resolved reads as not set, matching isset() semantics; database
     * and conversion errors still propagate.
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public static function isset(object $entity, string $name): bool
    {
        try {
            return null !== self::resolve($entity, $name);
        } catch (RelationException) {
            return false;
        }
    }

    public static function register(object $entity, RelationBinding $binding): void
    {
        self::bindings()[$entity] = $binding;
    }

    /**
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException
     * @throws RelationException
     * @throws TypeConversionException
     */
    public static function resolve(object $entity, string $name): mixed
    {
        $binding = self::bindings()[$entity] ?? throw RelationException::notLoaded($entity::class, $name);

        return $binding->resolve($entity, $name);
    }

    /**
     * @return WeakMap<object, RelationBinding>
     */
    private static function bindings(): WeakMap
    {
        return self::$bindings ??= new WeakMap();
    }
}
