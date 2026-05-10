<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Repository;

use Closure;
use Contenir\Db\Model\Entity\AbstractEntity;
use Contenir\Db\Model\Entity\EntityInterface;
use Contenir\Db\Model\Exception\InvalidArgumentException;
use Contenir\Db\Model\Exception\StaleEntityException;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Hydrator\RelationsHydrator;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Exception\RuntimeException;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql;
use Laminas\Db\Sql\TableIdentifier;
use Laminas\Db\TableGateway\TableGatewayInterface;
use Laminas\Hydrator\Aggregate\AggregateHydrator;
use Laminas\Hydrator\HydratorInterface;
use Throwable;

use function array_keys;
use function array_shift;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function iterator_to_array;
use function key;
use function sprintf;

/**
 * @template T of EntityInterface
 */
abstract class AbstractRepository implements TableGatewayInterface
{
    public const MODE_AUTO   = 'auto';
    public const MODE_INSERT = 'insert';
    public const MODE_UPDATE = 'update';

    /** @var string|array|TableIdentifier|null */
    protected TableIdentifier|string|array|null $table = null;

    protected Adapter $adapter;

    protected Sql\Sql $sql;

    /** @var T */
    protected EntityInterface $entityPrototype;

    protected RepositoryLookup $repositoryLookup;

    /**
     * List of default where conditions
     */
    protected array $where = [];

    /**
     * List of default sort order
     */
    protected array $order = [];

    protected ?int $lastInsertValue = null;

    /**
     * Memoised aggregate hydrator. Built on first {@see self::getHydrator()}
     * call and reused for the lifetime of the repository so the
     * RelationsHydrator's per-instance FK cache spans every query the
     * repository issues, not just one result set.
     */
    private ?HydratorInterface $hydrator = null;

    /**
     * Reference kept so {@see self::clearRelationsCache()} can invalidate
     * the cache after writes without having to walk the AggregateHydrator.
     */
    private ?RelationsHydrator $relationsHydrator = null;

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

    public function getTable(): TableIdentifier|string|array|null
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
        if ($this->hydrator !== null) {
            return $this->hydrator;
        }

        $relations = $this->entityPrototype->getRelations();
        $hydrator  = new AggregateHydrator();
        $hydrator->add(new EntityHydrator());

        if (count($relations)) {
            $this->relationsHydrator = new RelationsHydrator($this->repositoryLookup, $relations);
            $hydrator->add($this->relationsHydrator);
        }

        $this->hydrator = $hydrator;
        return $this->hydrator;
    }

    public function getResultSet(): ResultSetInterface
    {
        return new HydratingResultSet($this->getHydrator(), clone $this->entityPrototype);
    }

    /**
     * Discard any cached relation lookups held by this repository's
     * RelationsHydrator. Called automatically on writes from this
     * repository; users should call it manually after writes that go
     * through a different repository whose effects must be visible
     * here.
     */
    public function clearRelationsCache(): void
    {
        $this->relationsHydrator?->clearCache();
    }

    /** @return T */
    abstract public function create(iterable $data = []): EntityInterface;

    /**
     * Persist $entity as an INSERT or UPDATE. Modification tracking
     * decides which columns the UPDATE path writes; for INSERT, all
     * supplied columns are sent.
     *
     * The $refresh flag controls whether the entity is re-loaded from
     * the database after the write so it picks up DB-computed defaults,
     * triggers and concurrent writes. Default: false (single round-trip,
     * apply the auto-generated PK locally and {@see AbstractEntity::markClean()}).
     * Set true when you need the post-write database snapshot, at the
     * cost of an extra SELECT and the implicit assumption that no
     * concurrent writer changes the row between the INSERT/UPDATE and
     * the refetch (the call is wrapped in a transaction in that case).
     *
     * For optimistic locking, declare {@see AbstractEntity::$versionColumn}
     * on the entity. Update calls then use the loaded version as part
     * of the WHERE predicate, bump it via
     * {@see AbstractEntity::nextVersion()}, and throw
     * {@see StaleEntityException} when the row no longer matches.
     *
     * @param T      $entity
     * @param string $mode    one of MODE_AUTO, MODE_INSERT, MODE_UPDATE
     * @param bool   $refresh re-load the entity from the database after writing
     * @throws StaleEntityException When the entity is optimistically locked and the row has moved on.
     */
    public function save($entity, $mode = self::MODE_AUTO, bool $refresh = false): void
    {
        if ($refresh) {
            $this->transactional(function () use ($entity, $mode): void {
                $this->writeAndRefresh($entity, $mode);
            });
            return;
        }

        $this->writeAndMarkClean($entity, $mode);
    }

    /**
     * @param T $entity
     */
    private function writeAndMarkClean(EntityInterface $entity, string $mode): void
    {
        $data        = $entity->getModifiedArrayCopy();
        $primaryKeys = $entity->getPrimaryKeys();
        $resolved    = $this->resolveMode($mode, $primaryKeys);

        if ($resolved === self::MODE_INSERT) {
            $this->insert($data);
            if ($this->getLastInsertValue() && count($primaryKeys) === 1) {
                $entity->{key($primaryKeys)} = $this->getLastInsertValue();
            }
        } elseif (count($data) > 0) {
            $this->updateWithLocking($entity, $data, $primaryKeys);
        }

        $entity->markClean();
    }

    /**
     * @param T $entity
     */
    private function writeAndRefresh(EntityInterface $entity, string $mode): void
    {
        $data        = $entity->getModifiedArrayCopy();
        $existing    = $entity->getArrayCopy();
        $primaryKeys = $entity->getPrimaryKeys();
        $resolved    = $this->resolveMode($mode, $primaryKeys);

        if ($resolved === self::MODE_INSERT) {
            $this->insert($data);
            if ($this->getLastInsertValue() && count($primaryKeys) === 1) {
                $data[key($primaryKeys)] = $this->getLastInsertValue();
            }
        } elseif (count($data) > 0) {
            $this->updateWithLocking($entity, $data, $primaryKeys);
        }

        $newPrimaryKeys = [];
        foreach (array_keys($primaryKeys) as $key) {
            $newPrimaryKeys[$key] = $data[$key] ?? $existing[$key];
        }

        $this->synch($entity, $newPrimaryKeys);
    }

    /**
     * Apply optimistic-locking semantics if the entity declares a
     * version column; otherwise issue a plain UPDATE. Throws
     * {@see StaleEntityException} when an opt-in version check finds
     * zero rows.
     *
     * @param T                    $entity
     * @param array<string, mixed> $data        modified columns to write
     * @param array<string, mixed> $primaryKeys
     */
    private function updateWithLocking(EntityInterface $entity, array $data, array $primaryKeys): void
    {
        $versionColumn = $entity->getVersionColumn();
        if ($versionColumn === null) {
            $this->update($data, $primaryKeys);
            return;
        }

        $existing = $entity->getArrayCopy();
        $loaded   = $existing[$versionColumn] ?? null;
        $where    = $primaryKeys + [$versionColumn => $loaded];

        $data[$versionColumn] = $entity->nextVersion($loaded);

        $affected = $this->update($data, $where);
        if ($affected !== 1) {
            throw new StaleEntityException(sprintf(
                'Optimistic-lock failure: row matching %s with %s = %s no longer exists',
                self::primaryKeysToString($primaryKeys),
                $versionColumn,
                self::scalarToString($loaded)
            ));
        }

        $entity->{$versionColumn} = $data[$versionColumn];
    }

    /**
     * @param array<string, mixed> $primaryKeys
     */
    private function resolveMode(string $mode, array $primaryKeys): string
    {
        if ($mode !== self::MODE_AUTO) {
            return $mode;
        }

        // UPDATE only when every PK column carries a non-null value.
        // Falls back to INSERT for unsaved rows, partially-set composite
        // keys, and explicitly unassigned PKs. Treats 0 / '0' / '' as
        // legitimate PK values rather than as "unsaved".
        if ($primaryKeys === [] || in_array(null, $primaryKeys, true)) {
            return self::MODE_INSERT;
        }

        return self::MODE_UPDATE;
    }

    /**
     * Run $fn inside a database transaction. Re-entrant: nested calls on
     * a connection that is already in a transaction join the existing
     * one and only the outermost frame commits or rolls back.
     *
     * @template R
     * @param callable(): R $fn
     * @return R
     */
    public function transactional(callable $fn): mixed
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $isNested   = $connection->inTransaction();

        if (! $isNested) {
            $connection->beginTransaction();
        }

        try {
            $result = $fn();
        } catch (Throwable $e) {
            if (! $isNested) {
                $connection->rollback();
            }
            throw $e;
        }

        if (! $isNested) {
            $connection->commit();
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $primaryKeys
     */
    private static function primaryKeysToString(array $primaryKeys): string
    {
        $parts = [];
        foreach ($primaryKeys as $name => $value) {
            $parts[] = $name . '=' . self::scalarToString($value);
        }
        return '[' . implode(', ', $parts) . ']';
    }

    private static function scalarToString(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_string($value)) {
            return "'$value'";
        }
        return (string) $value;
    }

    /**
     * @param array $set
     */
    public function insert(
        $set
    ): int {
        $insert = $this->sql->insert();
        $insert->values($set);

        $affected = $this->executeInsert($insert);
        $this->clearRelationsCache();
        return $affected;
    }

    /**
     * Get last insert value
     */
    public function getLastInsertValue(): ?int
    {
        return $this->lastInsertValue;
    }

    /**
     * @param T $entity
     */
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
     * @throws RuntimeException
     * @todo add $columns support
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

        $statement = $this->sql->prepareStatementForSqlObject($insert);
        $result    = $statement->execute();

        $lastInsertValue       = $this->adapter->getDriver()->getConnection()->getLastGeneratedValue();
        $this->lastInsertValue = $lastInsertValue === null ? null : (int) $lastInsertValue;

        // Reset original table information in Insert instance, if necessary
        if ($unaliasedTable) {
            $insert->into($insertState['table']);
        }

        return $result->getAffectedRows();
    }

    /**
     * @param array                          $set
     * @param Closure|array|string|int|null  $where
     * @param array|null                     $joins
     */
    public function update($set, $where = null, ?array $joins = null): int
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

        $affected = $this->executeUpdate($update);
        $this->clearRelationsCache();
        return $affected;
    }

    /**
     * @throws RuntimeException
     * @todo add $columns support
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

    /**
     * @param Closure|array|string|int|null $where
     */
    public function select($where = null): Sql\Select
    {
        return $this->sql->select();
    }

    /**
     * @return iterable<T>&ResultSetInterface
     */
    public function selectWith(Sql\Select $select): ResultSetInterface
    {
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result    = $statement->execute();

        $resultSet = $this->getResultSet();
        $resultSet->initialize($result);
        $resultSet->buffer();

        return $resultSet;
    }

    /**
     * @param Closure|array|string|int $where
     */
    public function delete($where): int
    {
        $delete = $this->sql->delete();
        if ($where instanceof Closure) {
            $where($delete);
        } else {
            $delete->where($where);
        }

        $affected = $this->executeDelete($delete);
        $this->clearRelationsCache();
        return $affected;
    }

    /**
     * @throws RuntimeException
     * @todo add $columns support
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

    /**
     * @param Closure|array|string|int|null $where
     * @param array|string|null             $order
     * @return T|null
     */
    abstract public function findOne($where = null, $order = null, ?Sql\Select $select = null): ?EntityInterface;

    /**
     * @param string $fieldName
     * @param mixed  $value
     * @return T|null
     */
    public function findOneByField($fieldName, $value): ?EntityInterface
    {
        return $this->findByField($fieldName, $value)->current();
    }

    /**
     * Batch-load the named relations for the given entities so subsequent
     * relation property access does not trigger a per-row query (the N+1
     * pattern). Issues one SELECT per relation with a WHERE-IN clause over
     * the parent foreign-key values, then assigns the matching child rows
     * back onto each parent.
     *
     * Limitations:
     *  - Composite-key relations are not yet supported.
     *  - Relations declared with a `via` join table are not yet supported.
     *
     * @param iterable<EntityInterface> $entities
     * @param string[]                  $relationNames
     */
    public function preloadRelations(iterable $entities, array $relationNames): void
    {
        $entities = is_array($entities) ? $entities : iterator_to_array($entities);
        if ($entities === []) {
            return;
        }

        $relations = $this->entityPrototype->getRelations();

        foreach ($relationNames as $relationName) {
            if (! isset($relations[$relationName])) {
                throw new InvalidArgumentException(sprintf(
                    '"%s" is not a declared relation on %s',
                    $relationName,
                    $this->entityPrototype::class
                ));
            }

            $this->preloadRelation($entities, $relationName, $relations[$relationName]);
        }
    }

    /**
     * @param array<EntityInterface> $entities
     * @param array<string, mixed>   $config
     */
    private function preloadRelation(array $entities, string $relationName, array $config): void
    {
        if (! empty($config['via'])) {
            throw new RuntimeException(sprintf(
                'preloadRelations does not yet support "via" relations (relation "%s")',
                $relationName
            ));
        }

        $relationColumn      = (array) ($config['column'] ?? []);
        $relationTable       = $config['table'] ?? [];
        $relationTableClass  = $relationTable['class'] ?? null;
        $relationTableColumn = (array) ($relationTable['column'] ?? $relationColumn);
        $relationType        = $config['type'] ?? AbstractEntity::RELATION_MANY;

        if (count($relationColumn) !== 1 || count($relationTableColumn) !== 1) {
            throw new RuntimeException(sprintf(
                'preloadRelations does not yet support composite-key relations (relation "%s")',
                $relationName
            ));
        }

        if (! is_string($relationTableClass)) {
            throw new InvalidArgumentException(sprintf(
                'Relation "%s" is missing a table class',
                $relationName
            ));
        }

        $parentColumn = $relationColumn[0];
        $childColumn  = $relationTableColumn[0];

        $parentValues = [];
        foreach ($entities as $entity) {
            $value = $entity->{$parentColumn} ?? null;
            if ($value !== null) {
                $parentValues[(string) $value] = $value;
            }
        }

        if ($parentValues === []) {
            $this->assignPreloadedRelations($entities, $relationName, $parentColumn, $relationType, []);

            return;
        }

        $relatedRepository = $this->repositoryLookup->getContainer()->get($relationTableClass);
        $select            = $relatedRepository->select();
        $select->where->in($childColumn, array_values($parentValues));

        if (! empty($config['where'])) {
            $select->where($config['where']);
        }

        $relatedRepository->prepareSelect($select, null, $config['order'] ?? []);
        $rows = $relatedRepository->selectWith($select);

        $grouped = [];
        foreach ($rows as $row) {
            $key             = (string) ($row->{$childColumn} ?? '');
            $grouped[$key][] = $row;
        }

        $this->assignPreloadedRelations($entities, $relationName, $parentColumn, $relationType, $grouped);
    }

    /**
     * @param array<EntityInterface>          $entities
     * @param array<string, list<mixed>>      $grouped
     */
    private function assignPreloadedRelations(
        array $entities,
        string $relationName,
        string $parentColumn,
        string $relationType,
        array $grouped
    ): void {
        foreach ($entities as $entity) {
            $key      = (string) ($entity->{$parentColumn} ?? '');
            $children = $grouped[$key] ?? [];

            if ($relationType === AbstractEntity::RELATION_SINGLE) {
                // Empty array also marks the relation as "loaded" so the
                // event listener does not re-fire on next access.
                $entity->{$relationName} = $children === [] ? false : $children[0];
            } else {
                $entity->{$relationName} = $children;
            }
        }
    }

    /**
     * @param Closure|array|string|int|null $where
     * @param array|string|null             $order
     * @return iterable<T>&ResultSetInterface
     */
    public function find($where = null, $order = null, ?Sql\Select $select = null): ResultSetInterface
    {
        if ($select === null) {
            $select = $this->select();
        }

        $this->prepareSelect($select, $where, $order);

        return $this->selectWith($select);
    }

    /**
     * Find rows matching $fieldName = $value. If $value is an array the
     * predicate becomes WHERE $fieldName IN (...). $fieldName is validated
     * against the entity prototype's declared columns.
     *
     * @param string                $fieldName
     * @param mixed                 $value     scalar or list of scalars
     * @param Closure|array|null    $where
     * @param array|string|null     $order
     * @param Sql\Select|null       $select
     * @return iterable<T>&ResultSetInterface
     */
    public function findByField($fieldName, $value, $where = [], $order = null, $select = null): ResultSetInterface
    {
        $this->assertKnownColumn($fieldName);

        if ($select === null) {
            $select = $this->select();
        }

        if (is_array($value)) {
            $select->where->in($fieldName, $value);
        } else {
            $select->where([$fieldName => $value]);
        }

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

    /**
     * @param Closure|array|string|int|null $where
     * @param array|string|null             $order
     */
    public function prepareSelect(
        ?Sql\Select $select = null,
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
