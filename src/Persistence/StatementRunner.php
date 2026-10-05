<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

use Contenir\Db\Model\Exception\PersistenceException;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver\ResultInterface;
use PhpDb\Sql\PreparableSqlInterface;
use PhpDb\Sql\Sql;
use PhpDb\Sql\TableIdentifier;

/**
 * Thin wrapper over phpdb's Sql for the statements the persister issues.
 *
 * @internal
 */
final readonly class StatementRunner
{
    private Sql $sql;

    public function __construct(AdapterInterface $adapter)
    {
        $this->sql = new Sql($adapter);
    }

    /**
     * @param array<string, int|float|string|bool|null> $where
     *
     * @return int affected rows
     *
     * @throws PersistenceException
     */
    public function delete(TableIdentifier $table, array $where): int
    {
        return $this->execute($this->sql->delete($table)->where($where))->getAffectedRows();
    }

    /**
     * @param array<string, int|float|string|bool|null> $values
     *
     * @throws PersistenceException
     */
    public function insert(TableIdentifier $table, array $values): ResultInterface
    {
        return $this->execute($this->sql->insert($table)->values($values));
    }

    /**
     * @param array<string, int|float|string|bool|null> $set
     * @param array<string, int|float|string|bool|null> $where
     *
     * @return int affected rows
     *
     * @throws PersistenceException
     */
    public function update(TableIdentifier $table, array $set, array $where): int
    {
        return $this->execute($this->sql->update($table)->set($set)->where($where))->getAffectedRows();
    }

    /**
     * @throws PersistenceException
     */
    private function execute(PreparableSqlInterface $statement): ResultInterface
    {
        return (
            $this->sql->prepareStatementForSqlObject($statement)->execute() ?? throw PersistenceException::noResult()
        );
    }
}
