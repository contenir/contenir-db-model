<?php

namespace Contenir\Db\Model\Repository;

use ArrayObject;
use Closure;
use Contenir\Db\Model\Entity\AbstractEntity;
use Contenir\Db\Model\Entity\EntityInterface;
use Contenir\Db\Model\Exception\InvalidArgumentException;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Hydrator\RelationsHydrator;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Exception\RuntimeException;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Insert;
use Laminas\Db\Sql\TableIdentifier;
use Laminas\Db\Sql\Update;
use Laminas\Db\TableGateway\TableGatewayInterface;
use Laminas\Hydrator\Aggregate\AggregateHydrator;
use Laminas\Hydrator\HydratorInterface;

abstract class AbstractRepository implements TableGatewayInterface
{
    public const MODE_AUTO   = 'auto';
    public const MODE_INSERT = 'insert';
    public const MODE_UPDATE = 'update';

    /**
     * @var string|array|TableIdentifier|null
     */
    protected TableIdentifier|string|array|null $table = null;

    /**
     * @var Adapter
     */
    protected Adapter $adapter;

    /**
     * @var Sql\Sql
     */
    protected Sql\Sql $sql;

    /**
     * @var AbstractEntity
     */
    protected EntityInterface $entityPrototype;

    /**
     * @var RepositoryLookup
     */
    protected RepositoryLookup $repositoryLookup;

    /**
     * List of default where conditions
     */
    protected array $where = [];

    /**
     * List of default sort order
     */
    protected array $order = [];

    /**
     *
     * @var int|null
     */
    protected ?int $lastInsertValue = null;

    public function __construct(
        Adapter $adapter,
        AbstractEntity $entityPrototype,
        RepositoryLookup $repositoryLookup
    ) {
        $this->adapter          = $adapter;
        $this->sql              = new Sql\Sql($this->adapter, $this->table);
        $this->entityPrototype  = $entityPrototype;
        $this->repositoryLookup = $repositoryLookup;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getAdapter(): Adapter
    {
        return $this->adapter;
    }

    public function getSql(): Sql\Sql
    {
        return $this->sql;
    }

    public function getHydrator(): HydratorInterface
    {
        $relations = $this->entityPrototype->getRelations();

        $hydrator = new AggregateHydrator();
        $hydrator->add(new EntityHydrator());

        if (count($relations)) {
            $hydrator->add(new RelationsHydrator($this->repositoryLookup, $relations));
        }

        return $hydrator;
    }

    public function getResultSet(): ResultSetInterface
    {
        return new HydratingResultSet($this->getHydrator(), clone $this->entityPrototype);
    }

    abstract public function create(iterable $data = []): EntityInterface;

    public function save($entity, $mode = self::MODE_AUTO): void
    {
        $data     = $entity->getModifiedArrayCopy();
        $existing = $entity->getArrayCopy();

        $primaryKeys = $entity->getPrimaryKeys();

        if ($mode == self::MODE_AUTO) {
            $mode = (count(array_filter($primaryKeys)) == 0) ? self::MODE_INSERT : self::MODE_UPDATE;
        }

        switch ($mode) {
            case self::MODE_INSERT:
                $this->insert($data);
                if ($this->getLastInsertValue() && count($primaryKeys) == 1) {
                    $data[key($primaryKeys)] = $this->getLastInsertValue();
                }
                break;

            case self::MODE_UPDATE:
                if (count($data)) {
                    $this->update($data, $primaryKeys);
                }
                break;
        }

        $newPrimaryKeys = [];
        foreach (array_keys($primaryKeys) as $key) {
            $newPrimaryKeys[$key] = $data[$key] ?? $existing[$key];
        }

        $this->synch($entity, $newPrimaryKeys);
    }

    public function insert(
        $set
    ): int {
        $insert = $this->sql->insert();
        $insert->values($set);

        return $this->executeInsert($insert);
    }

    /**
     * Get last insert value
     *
     * @return int|null
     */
    public function getLastInsertValue(): ?int
    {
        return $this->lastInsertValue;
    }

    public function synch(AbstractEntity $entity, ?array $primaryKeys = null): void
    {
        if ($primaryKeys === null) {
            $primaryKeys = $entity->getPrimaryKeys();
        }
        $result = $this->findOne($primaryKeys);
        if ($result === null) {
            throw new RuntimeException('No row found');
        }

        $data = $result->getArrayCopy();
        $entity->synch($data);
        $this->getHydrator()->hydrate($data, $entity);
    }

    /**
     * @param Insert $insert
     *
     * @throws RuntimeException
     * @return int
     * @todo add $columns support
     *
     */
    protected function executeInsert(Sql\Insert $insert): int
    {
        $insertState = $insert->getRawState();
        $this->assertTableMatches($insertState['table']);

        // Most RDBMS solutions do not allow using table aliases in INSERTs
        // See https://github.com/zendframework/zf2/issues/7311
        $unaliasedTable = false;
        if (is_array($insertState['table'])) {
            $tableData      = array_values($insertState['table']);
            $unaliasedTable = array_shift($tableData);
            $insert->into($unaliasedTable);
        }

        $statement             = $this->sql->prepareStatementForSqlObject($insert);
        $result                = $statement->execute();
        $this->lastInsertValue = $this->adapter->getDriver()->getConnection()->getLastGeneratedValue();

        // Reset original table information in Insert instance, if necessary
        if ($unaliasedTable) {
            $insert->into($insertState['table']);
        }

        return $result->getAffectedRows();
    }

    public function update($set, $where = null, array $joins = null): int
    {
        $sql    = $this->sql;
        $update = $sql->update();
        $update->set($set);
        if ($where !== null) {
            $update->where($where);
        }

        if ($joins) {
            foreach ($joins as $join) {
                $type = $join['type'] ?? Sql\Select::JOIN_INNER;
                $update->join($join['name'], $join['on'], $type);
            }
        }

        return $this->executeUpdate($update);
    }

    /**
     * @param Update $update
     *
     * @throws RuntimeException
     * @return int
     * @todo add $columns support
     *
     */
    protected function executeUpdate(Sql\Update $update): int
    {
        $updateState = $update->getRawState();
        $this->assertTableMatches($updateState['table']);

        $unaliasedTable = false;
        if (is_array($updateState['table'])) {
            $tableData      = array_values($updateState['table']);
            $unaliasedTable = array_shift($tableData);
            $update->table($unaliasedTable);
        }

        $statement = $this->sql->prepareStatementForSqlObject($update);

        $result = $statement->execute();

        // Reset original table information in Update instance, if necessary
        if ($unaliasedTable) {
            $update->table($updateState['table']);
        }

        return $result->getAffectedRows();
    }

    public function select($where = null): Sql\Select
    {
        return $this->sql->select();
    }

    public function selectWith(Sql\Select $select): ResultSetInterface
    {
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result    = $statement->execute();

        $resultSet = $this->getResultSet();
        $resultSet->initialize($result);
        $resultSet->buffer();

        return $resultSet;
    }

    public function delete($where): int
    {
        $delete = $this->sql->delete();
        if ($where instanceof Closure) {
            $where($delete);
        } else {
            $delete->where($where);
        }

        return $this->executeDelete($delete);
    }

    /**
     * @param Delete $delete
     *
     * @throws RuntimeException
     * @return int
     * @todo add $columns support
     *
     */
    protected function executeDelete(Sql\Delete $delete): int
    {
        $deleteState = $delete->getRawState();
        $this->assertTableMatches($deleteState['table']);

        $unaliasedTable = false;
        if (is_array($deleteState['table'])) {
            $tableData      = array_values($deleteState['table']);
            $unaliasedTable = array_shift($tableData);
            $delete->from($unaliasedTable);
        }

        $statement = $this->sql->prepareStatementForSqlObject($delete);
        $result    = $statement->execute();

        // Reset original table information in Delete instance, if necessary
        if ($unaliasedTable) {
            $delete->from($deleteState['table']);
        }

        return $result->getAffectedRows();
    }

    abstract public function findOne($where = null, $order = null, Sql\Select $select = null): ?EntityInterface;

    public function findOneByField($fieldName, $value): EntityInterface|ArrayObject|array|null
    {
        return $this->findByField($fieldName, $value)->current();
    }

    public function find($where = null, $order = null, Sql\Select $select = null): ResultSetInterface
    {
        if ($select === null) {
            $select = $this->select();
        }

        $this->prepareSelect($select, $where, $order);

        return $this->selectWith($select);
    }

    public function findByField($fieldName, $value, $where = [], $order = null, $select = null): ResultSetInterface
    {
        $this->assertKnownColumn($fieldName);

        if ($select === null) {
            $select = $this->select();
        }

        $select->where([$fieldName => $value]);

        if ($where instanceof Closure) {
            $where($select);
        } elseif ($where !== null) {
            $select->where($where);
        }

        return $this->find(null, $order, $select);
    }

    /**
     * Reject column names that are not declared on the entity prototype.
     * Stops caller-supplied $fieldName (e.g. from query strings) from
     * smuggling SQL into the predicate's left-hand side.
     */
    private function assertKnownColumn(string $fieldName): void
    {
        if (! in_array($fieldName, $this->entityPrototype->getColumns(), true)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a known column on %s',
                $fieldName,
                $this->entityPrototype::class
            ));
        }
    }

    /**
     * Verify that $candidate refers to the same table as the repository,
     * regardless of whether it is expressed as a string, an
     * alias-keyed array, or a TableIdentifier.
     */
    private function assertTableMatches(mixed $candidate): void
    {
        if ($this->canonicalTableName($candidate) !== $this->canonicalTableName($this->table)) {
            throw new RuntimeException(
                'The table name of the provided SQL object must match that of the repository'
            );
        }
    }

    private function canonicalTableName(mixed $table): string
    {
        if ($table instanceof TableIdentifier) {
            return $table->getTable();
        }

        if (is_array($table)) {
            // alias => table
            $values = array_values($table);
            $first  = $values[0] ?? '';

            return $first instanceof TableIdentifier ? $first->getTable() : (string) $first;
        }

        return (string) ($table ?? '');
    }

    public function prepareSelect(
        Sql\Select $select = null,
        $where = [],
        $order = []
    ): ?Sql\Select {
        if ($select === null) {
            $select = $this->select();
        }

        if (! empty($where)) {
            $select->where($where);
        }

        if ($this->where) {
            $select->where($this->where);
        }

        if (! empty($order)) {
            // Pass through to Select::order(); it handles strings,
            // [column => direction] and [column, ...] arrays, and
            // Sql\Expression instances. Identifier quoting is applied
            // by the SQL platform.
            $select->order($order);
        }

        if ($this->order) {
            $select->order($this->order);
        }

        return $select;
    }
}
