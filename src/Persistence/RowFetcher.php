<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Generator;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Select;
use PhpDb\Sql\Sql;

/**
 * Executes selects and yields raw rows as column => value arrays.
 *
 * @internal
 */
final readonly class RowFetcher
{
    private Sql $sql;

    public function __construct(AdapterInterface $adapter)
    {
        $this->sql = new Sql($adapter);
    }

    /**
     * @param iterable<int, array<string, mixed>> $result
     *
     * @return Generator<int, array<string, mixed>>
     */
    private static function iterate(iterable $result): Generator
    {
        foreach ($result as $row) {
            yield $row;
        }
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T>                         $metadata
     * @param array<string, int|float|string|bool|null> $identifier
     *
     * @return array<string, mixed>|null
     *
     * @throws PersistenceException
     */
    public function fetchById(EntityMetadata $metadata, array $identifier): ?array
    {
        return $this->rows($this->select($metadata)->where($identifier)->limit(1))->current();
    }

    /**
     * Execute $select immediately and return its rows lazily.
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @throws PersistenceException When the driver returns no result.
     */
    public function rows(Select $select): Generator
    {
        /** @var iterable<int, array<string, mixed>>|null $result phpdb's PDO results fetch associative arrays */
        $result = $this->sql->prepareStatementForSqlObject($select)->execute();
        if (null === $result) {
            throw PersistenceException::noResult();
        }

        return self::iterate($result);
    }

    /**
     * A select over the entity's table with every mapped column listed
     * explicitly, so unmapped columns are never fetched.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     */
    public function select(EntityMetadata $metadata): Select
    {
        return $this->sql->select($metadata->getTableIdentifier())->columns($metadata->getColumnNames());
    }
}
