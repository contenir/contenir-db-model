<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\QueryException;
use Contenir\Db\Model\Exception\RelationException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Query\CriteriaTranslator;
use Contenir\Db\Model\Query\EntityReader;
use Generator;
use PhpDb\Sql\Select;

use function array_values;

/**
 * Finders for one entity class. Every entity returned is managed by the
 * owning {@see EntityManager}: rows already loaded come back as the same
 * instances.
 *
 * Criteria and ordering are keyed by property name. Criteria values may
 * be scalars or objects (converted through the type registry), lists
 * (IN) or null (IS NULL).
 *
 * Extend this class for custom repositories:
 *
 *     final class UserRepository extends Repository
 *     {
 *         public function __construct(EntityManager $em)
 *         {
 *             parent::__construct($em, User::class);
 *         }
 *     }
 *
 * @api
 *
 * @template-covariant T of object
 */
class Repository
{
    /**
     * @var EntityMetadata<T>
     */
    protected readonly EntityMetadata $metadata;

    private readonly EntityReader $reader;

    private readonly CriteriaTranslator $criteria;

    /**
     * @param class-string<T> $className
     *
     * @throws MappingException
     */
    public function __construct(
        protected readonly EntityManager $em,
        string $className,
    ) {
        $context        = $em->queryContext();
        $this->metadata = $context->metadata->getMetadataFor($className);
        $this->reader   = $context->reader;
        $this->criteria = $context->criteria;
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @throws PersistenceException
     * @throws QueryException
     * @throws TypeConversionException
     */
    public function count(array $criteria = []): int
    {
        return $this->reader->count($this->metadata, $this->criteria->where($this->metadata, $criteria));
    }

    /**
     * A select over the entity's table listing every mapped column, for
     * custom queries run through {@see self::fetch()} or
     * {@see self::fetchOne()}. Keep the primary-key columns selected.
     */
    public function createSelect(): Select
    {
        return $this->reader->select($this->metadata);
    }

    /**
     * @return list<T>
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function fetch(Select $select): array
    {
        return $this->reader->all($this->metadata, $select);
    }

    /**
     * @return T|null
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function fetchOne(Select $select): ?object
    {
        return $this->reader->one($this->metadata, $select);
    }

    /**
     * Find by primary key: a scalar for single-column keys, or an array
     * keyed by property name for composite keys. Already-loaded entities
     * are returned without a query.
     *
     * @param int|string|array<string, mixed> $id
     *
     * @return T|null
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws QueryException
     * @throws TypeConversionException
     */
    public function find(int|string|array $id): ?object
    {
        $identifier = $this->criteria->identifier($this->metadata, $id);
        $managed    = $this->em->queryContext()->identityMap->get($this->metadata->className, $identifier);
        if (null !== $managed) {
            return $managed;
        }

        return $this->reader->one($this->metadata, $this->reader->select($this->metadata)->where($identifier));
    }

    /**
     * @param array<string, mixed>  $criteria
     * @param array<string, string> $orderBy
     *
     * @return list<T>
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws QueryException
     * @throws TypeConversionException
     */
    public function findBy(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $select = $this->criteria->apply($this->metadata, $this->reader->select($this->metadata), $criteria, $orderBy);
        if (null !== $limit) {
            $select->limit($limit);
        }

        if (null !== $offset) {
            $select->offset($offset);
        }

        return $this->reader->all($this->metadata, $select);
    }

    /**
     * @param array<string, mixed>  $criteria
     * @param array<string, string> $orderBy
     *
     * @return T|null
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws QueryException
     * @throws TypeConversionException
     */
    public function findOneBy(array $criteria, array $orderBy = []): ?object
    {
        return $this->reader->one(
            $this->metadata,
            $this->criteria->apply($this->metadata, $this->reader->select($this->metadata), $criteria, $orderBy),
        );
    }

    /**
     * Load the named relations for all $entities in one query per
     * relation (and per level of a dotted path such as "orders.items"),
     * avoiding a query per entity when iterating. Already loaded relations
     * are replaced.
     *
     * @param iterable<object> $entities entities of this repository's class
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException          When a path names an undeclared relation.
     * @throws PersistenceException
     * @throws RelationException         When an entity is of another class, or a required relation has no row.
     * @throws TypeConversionException
     */
    public function preload(iterable $entities, string ...$paths): void
    {
        $list = [];
        foreach ($entities as $entity) {
            if (! $entity instanceof $this->metadata->className) {
                throw RelationException::unexpectedEntity($this->metadata->className, $entity::class);
            }

            $list[] = $entity;
        }

        if ([] === $list || [] === $paths) {
            return;
        }

        $this->em->queryContext()->preloader->preload($this->metadata, $list, array_values($paths));
    }

    /**
     * Yield matching entities one at a time without buffering the result
     * set. Each entity is still registered in the identity map; clear the
     * entity manager periodically when streaming large tables.
     *
     * @param array<string, mixed>  $criteria
     * @param array<string, string> $orderBy
     *
     * @return Generator<int, T>
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws QueryException
     * @throws TypeConversionException
     */
    public function stream(array $criteria = [], array $orderBy = []): Generator
    {
        return $this->reader->stream(
            $this->metadata,
            $this->criteria->apply($this->metadata, $this->reader->select($this->metadata), $criteria, $orderBy),
        );
    }
}
