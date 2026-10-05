<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Query;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Identity\EntityLoader;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Persistence\RowFetcher;
use Generator;
use PhpDb\Sql\Expression;
use PhpDb\Sql\Select;

/**
 * Runs selects and turns their rows into managed entities through the
 * identity map.
 *
 * @internal
 */
final readonly class EntityReader
{
    public function __construct(
        private RowFetcher $rows,
        private EntityLoader $loader,
    ) {}

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @return list<T>
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function all(EntityMetadata $metadata, Select $select): array
    {
        $entities = [];
        foreach ($this->stream($metadata, $select) as $entity) {
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * @param array<string, int|float|string|bool|list<int|float|string|bool|null>|null> $where
     *
     * @throws PersistenceException
     */
    public function count(EntityMetadata $metadata, array $where): int
    {
        $select = $this->rows->select($metadata)->columns(['count' => new Expression('COUNT(*)')]);
        if ([] !== $where) {
            $select->where($where);
        }

        $row = $this->rows->rows($select)->current();

        return (int) ($row['count'] ?? 0);
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @return T|null
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function one(EntityMetadata $metadata, Select $select): ?object
    {
        return $this->stream($metadata, $select->limit(1))->current();
    }

    /**
     * The select to build a query on: a clone of $from when given (so the
     * caller's object is never modified), otherwise a select over the
     * entity's table listing every mapped column.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     */
    public function select(EntityMetadata $metadata, ?Select $from = null): Select
    {
        return null === $from ? $this->rows->select($metadata) : clone $from;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @return Generator<int, T>
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function stream(EntityMetadata $metadata, Select $select): Generator
    {
        foreach ($this->rows->rows($select) as $row) {
            yield $this->loader->load($metadata, $row);
        }
    }
}
