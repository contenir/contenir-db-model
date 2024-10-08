<?php

namespace Contenir\Db\Model\Repository;

use Contenir\Db\Model\Entity\AbstractEntity;
use Contenir\Db\Model\Entity\EntityInterface;
use Contenir\Db\Model\Hydrator\RelationsHydrator;
use Contenir\Db\Model\Repository\RepositoryLookup;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Exception\RuntimeException;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\TableGateway\TableGatewayInterface;
use Laminas\Hydrator\Aggregate\AggregateHydrator;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Hydrator\ObjectPropertyHydrator;
use Laminas\Paginator\Adapter\LaminasDb\DbSelect;

abstract class AbstractRepository implements TableGatewayInterface
{
    public const MODE_AUTO   = 'auto';
    public const MODE_INSERT = 'insert';
    public const MODE_UPDATE = 'update';

    /**
     * @var string|array|TableIdentifier
     */
    protected $table = null;

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
     * List of abstract relation fields
     */
    protected $entityRelations = null;

    /**
     * List of default where conditions
     */
    protected $where = [
    ];

    /**
     * List of default sort order
     */
    protected $order = [
    ];

    /**
     *
     * @var int
     */
    protected $lastInsertValue = null;

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

    public function getSql()
    {
        return $this->sql;
    }

    public function getHydrator(): HydratorInterface
    {
        $relations = $this->entityPrototype->getRelations();

        $hydrator = new AggregateHydrator();
        $hydrator->add(new ObjectPropertyHydrator());

        if (count($relations)) {
            $hydrator->add(new RelationsHydrator($this->repositoryLookup, $relations));
        }

        return $hydrator;
    }

    public function getResultSet(): ResultSetInterface
    {
        $relations = $this->entityPrototype->getRelations();

        $hydrator = new AggregateHydrator();
        $hydrator->add(new ObjectPropertyHydrator());

        if (count($relations)) {
            $hydrator->add(new RelationsHydrator($this->repositoryLookup, $relations));
        }

        return new HydratingResultSet($hydrator, clone $this->entityPrototype);
    }

    public function create(iterable $data = []): EntityInterface
    {
        $row = clone $this->entityPrototype;
        $row->exchangeArray($data);

        return $row;
    }

    public function save($entity, $mode = self::MODE_AUTO)
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

        $entity->exchangeArray($this->findOne($newPrimaryKeys)->getArrayCopy());
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
     * @return int
     */
    public function getLastInsertValue()
    {
        return $this->lastInsertValue;
    }

    public function synch(AbstractEntity $entity, ?array $primaryKeys = null)
    {
        if ($primaryKeys === null) {
            $primaryKeys = $entity->getPrimaryKeys();
        }
        $result = $this->findOne($primaryKeys);
        if ($result === null) {
            throw new RuntimeException('No row found');
        }

        $data = $this->findOne($primaryKeys)->getArrayCopy();
        $entity->synch($data);
        $this->getHydrator()->hydrate($data, $entity);
    }

    /**
     * @param Insert $insert
     *
     * @throws Exception\RuntimeException
     * @return int
     * @todo add $columns support
     *
     */
    protected function executeInsert(Sql\Insert $insert)
    {
        $insertState = $insert->getRawState();
        if ($insertState['table'] != $this->table) {
            throw new RuntimeException(
                'The table name of the provided Insert object must match that of the table'
            );
        }

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
                $type = isset($join['type']) ? $join['type'] : Sql\Select::JOIN_INNER;
                $update->join($join['name'], $join['on'], $type);
            }
        }

        return $this->executeUpdate($update);
    }

    /**
     * @param Update $update
     *
     * @throws Exception\RuntimeException
     * @return int
     * @todo add $columns support
     *
     */
    protected function executeUpdate(Sql\Update $update)
    {
        $updateState = $update->getRawState();
        if ($updateState['table'] != $this->table) {
            throw new RuntimeException(
                'The table name of the provided Update object must match that of the table'
            );
        }

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

    public function selectWith(Sql\Select $select)
    {
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result    = $statement->execute();

        /** @var Laminas\Db\ResultSet\ResultSet $resultSet */
        $resultSet = $this->getResultSet();
        $resultSet->initialize($result);
        $resultSet->buffer();

        return $resultSet;
    }

    public function delete($where): int
    {
        $delete = $this->sql->delete();
        if ($where instanceof \Closure) {
            $where($delete);
        } else {
            $delete->where($where);
        }

        return $this->executeDelete($delete);
    }

    /**
     * @param Delete $delete
     *
     * @throws Exception\RuntimeException
     * @return int
     * @todo add $columns support
     *
     */
    protected function executeDelete(Sql\Delete $delete)
    {
        $deleteState = $delete->getRawState();
        if ($deleteState['table'] != $this->table) {
            throw new RuntimeException(
                'The table name of the provided Delete object must match that of the table'
            );
        }

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

    public function findOne($where = null, $order = null, Sql\Select $select = null)
    {
        return $this->find($where, $order, $select)->current();
    }

    public function find($where = null, $order = null, Sql\Select $select = null)
    {
        if ($select === null) {
            $select = $this->select();
        }

        $this->prepareSelect($select, $where, $order);

        return $this->selectWith($select);
    }

    public function findOneByField($fieldName, $value)
    {
        return $this->findByField($fieldName, $value)->current();
    }

    public function findByField($fieldName, $value, $where = [], $order = null, $select = null)
    {
        if ($select === null) {
            $select = $this->select();
        }

        $select->where([$fieldName => $value]);

        if ($where instanceof \Closure) {
            $where($select);
        } elseif ($where !== null) {
            $select->where($where);
        }

        return $this->find($where, $order, $select);
    }

    public function prepareSelect(
        Sql\Select $select = null,
        $where = [],
        $order = []
    ) {
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
            if (is_array($order)) {
                foreach ($order as $part) {
                    $select->order(new Sql\Expression($part));
                }
            } else {
                $select->order(new Sql\Expression($order));
            }
        }

        if ($this->order) {
            $select->order($this->order);
        }

        return $select;
    }
}
