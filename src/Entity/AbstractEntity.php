<?php

namespace Contenir\Db\Model\Entity;

use Contenir\Db\Model\Exception\RuntimeException;
use InvalidArgumentException;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareTrait;

abstract class AbstractEntity implements EntityInterface
{
    use EventManagerAwareTrait;

    public const RELATION_SINGLE = 'single';
    public const RELATION_MANY   = 'many';

    /**
     * Primary Keys for table
     *
     * @var array
     */
    protected array $primaryKeys = [];

    /**
     * List of table columns
     *
     * @var array
     */
    protected array $columns = [];

    /**
     * Table row data
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Indicates if table row data has been modified programmatically
     *
     * @var array
     */
    protected array $modifiedDataFields = [];

    /**
     * Lookup for table relations
     *
     * @var array
     */
    protected array $relations = [];

    /**
     * EventsManager
     *
     * @var EventManager
     */
    protected $events;

    public function __construct(iterable $data = [])
    {
        $this->reset();
        $this->populate($data);
    }

    public function getPrimaryKeys(): array
    {
        return array_intersect_key(
            $this->data,
            array_combine($this->primaryKeys, $this->primaryKeys)
        );
    }

    /**
     * Retrieve row field value
     *
     * @param string $columnName The user-specified column name.
     *
     * @throws RuntimeException if the $columnName is not a column in the row.
     * @return string             The corresponding column value.
     */
    public function __get(string $columnName)
    {
        if (array_key_exists($columnName, $this->relations) && is_null($this->data[$columnName] ?? null)) {
            $this->getEventManager()->trigger('loadRelation', $this, [
                'relation' => $columnName
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
     *
     * @return void
     */
    public function __set(string $columnName, mixed $value): void
    {
        if (array_key_exists($columnName, $this->data)) {
            $this->modifiedDataFields[$columnName] = ($this->data[$columnName] !== $value);
            $this->data[$columnName]               = $value;
        }
    }

    /**
     * Unset row field value
     *
     * @param string $columnName The column key.
     *
     * @return void
     */
    public function __unset(string $columnName): void
    {
        if (! array_key_exists($columnName, $this->columns)) {
            throw new InvalidArgumentException("Specified column \"$columnName\" is not in the row");
        }

        unset($this->data[$columnName]);
    }

    /**
     * Test existence of row field
     *
     * @param string $columnName The column key.
     *
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
            'data',
            'modifiedDataFields'
        ];
    }

    /**
     * @param mixed $array
     *
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
     *
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
     * @param mixed $array
     *
     * @return self Provides a fluent interface
     */
    public function synch(iterable $array): AbstractEntity
    {
        $this->reset();

        return $this->populate($array);
    }

    /**
     * Return a copy of the row array
     *
     * @return array
     */
    public function getArrayCopy(): array
    {
        return $this->data;
    }

    /**
     * Return a copy of the row array only for modified columns
     *
     * @return array
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
     *
     * @return array
     */
    public function getRelations(): array
    {
        return $this->relations;
    }
}
