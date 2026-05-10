<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Entity;

use Contenir\Db\Model\Exception\RuntimeException;
use InvalidArgumentException;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\EventManager\EventManagerInterface;

use function array_combine;
use function array_diff_key;
use function array_fill_keys;
use function array_filter;
use function array_intersect;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_values;
use function implode;
use function sprintf;

abstract class AbstractEntity implements EntityInterface
{
    use EventManagerAwareTrait;

    public const RELATION_SINGLE = 'single';
    public const RELATION_MANY   = 'many';

    /**
     * Primary Keys for table
     */
    protected array $primaryKeys = [];

    /**
     * List of table columns
     */
    protected array $columns = [];

    /**
     * Table row data
     */
    protected array $data = [];

    /**
     * Indicates if table row data has been modified programmatically
     */
    protected array $modifiedDataFields = [];

    /**
     * Lookup for table relations
     */
    protected array $relations = [];

    /**
     * Optional name of an integer / numeric column used for optimistic
     * concurrency control. When set, repository UPDATEs use the column's
     * current value as part of the WHERE predicate and bump it through
     * {@see self::nextVersion()} on each successful write.
     */
    protected ?string $versionColumn = null;

    /**
     * EventsManager
     *
     * @var EventManager
     */
    protected $events;

    public function __construct(iterable $data = [])
    {
        $collisions = array_intersect($this->columns, array_keys($this->relations));
        if ($collisions !== []) {
            throw new InvalidArgumentException(sprintf(
                'Entity columns and relations must not share names; got: %s',
                implode(', ', $collisions)
            ));
        }

        $this->reset();
        $this->populate($data);
    }

    /**
     * Lazily provide an EventManager so accessing a relation never crashes
     * on a freshly-instantiated entity that hasn't been hydrated through a
     * repository.
     */
    public function getEventManager(): EventManagerInterface
    {
        if ($this->events === null) {
            $this->setEventManager(new EventManager());
        }

        return $this->events;
    }

    public function getPrimaryKeys(): array
    {
        $result = [];
        foreach ($this->primaryKeys as $key) {
            $result[$key] = $this->data[$key] ?? null;
        }

        return $result;
    }

    /**
     * Retrieve row field value
     *
     * @param string $columnName The user-specified column name.
     * @return mixed              The corresponding column value.
     * @throws RuntimeException If the $columnName is not a column in the row.
     */
    public function __get(string $columnName)
    {
        if (array_key_exists($columnName, $this->relations) && ($this->data[$columnName] ?? null) === null) {
            $this->getEventManager()->trigger('loadRelation', $this, [
                'relation' => $columnName,
            ]);
        }

        if (array_key_exists($columnName, $this->data)) {
            return $this->data[$columnName];
        }

        throw new RuntimeException(sprintf(
            "Specified column \"%s\" is not in the row",
            $columnName
        ));
    }

    /**
     * Set row field value
     *
     * @param string $columnName The column key.
     * @param mixed  $value      The value for the property.
     */
    public function __set(string $columnName, mixed $value): void
    {
        if (array_key_exists($columnName, $this->data)) {
            $this->modifiedDataFields[$columnName] = $this->data[$columnName] !== $value;
            $this->data[$columnName]               = $value;
        }
    }

    /**
     * Unset row field value
     *
     * @param string $columnName The column key.
     */
    public function __unset(string $columnName): void
    {
        if (! array_key_exists($columnName, $this->data)) {
            throw new InvalidArgumentException("Specified column \"$columnName\" is not in the row");
        }

        unset($this->data[$columnName], $this->modifiedDataFields[$columnName]);
    }

    /**
     * Test existence of row field
     *
     * @param string $columnName The column key.
     * @return boolean
     */
    public function __isset(string $columnName)
    {
        return array_key_exists($columnName, $this->data);
    }

    /**
     * Store table, primary key and data in serialized object
     *
     * @return array
     */
    public function __sleep()
    {
        return [
            'primaryKeys',
            'columns',
            'relations',
            'data',
            'modifiedDataFields',
        ];
    }

    /**
     * @param mixed $array
     * @return self Provides a fluent interface
     */
    public function exchangeArray(array $array): AbstractEntity
    {
        return $this->populate($array);
    }

    /**
     * Populate Data
     *
     * @param array $rowData
     * @return self Provides a fluent interface
     */
    public function populate(iterable $rowData): self
    {
        foreach ($rowData as $key => $value) {
            if ($this->__isset($key)) {
                $this->__set($key, $value);
            }
        }

        return $this;
    }

    protected function reset(): void
    {
        $columns = array_merge(
            array_values($this->columns),
            array_keys($this->relations)
        );

        $this->data               = array_fill_keys($columns, null);
        $this->modifiedDataFields = array_fill_keys($columns, false);
    }

    /**
     * Replace the entity's data with $array and mark every column as
     * unmodified, treating the supplied data as the canonical state of the
     * row (e.g. as just loaded from storage).
     *
     * @return self Provides a fluent interface
     */
    public function synch(iterable $array): AbstractEntity
    {
        $this->reset();
        $this->populate($array);
        $this->markClean();

        return $this;
    }

    /**
     * Reset the modification tracking flags so that the current data is
     * treated as the canonical state of the row. No data is modified.
     *
     * @return self Provides a fluent interface
     */
    public function markClean(): self
    {
        $this->modifiedDataFields = array_fill_keys(
            array_keys($this->modifiedDataFields),
            false
        );

        return $this;
    }

    /**
     * Return a copy of the row array
     */
    public function getArrayCopy(): array
    {
        return $this->data;
    }

    /**
     * Return a copy of the row array only for modified columns
     */
    public function getModifiedArrayCopy(): array
    {
        $columns = array_intersect_key(
            $this->data,
            array_filter($this->modifiedDataFields)
        );

        return array_diff_key(
            $columns,
            array_combine(array_keys($this->relations), array_keys($this->relations))
        );
    }

    /**
     * Return the column definitions of the table row
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Return the list of column names declared by the entity.
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        return array_values($this->columns);
    }

    /**
     * Name of the optimistic-locking version column, or null if optimistic
     * locking is not enabled for this entity.
     */
    public function getVersionColumn(): ?string
    {
        return $this->versionColumn;
    }

    /**
     * Compute the next version value given the current one. Override on
     * subclasses with non-integer version semantics (e.g. timestamps).
     */
    public function nextVersion(mixed $current): mixed
    {
        return ((int) $current) + 1;
    }
}
