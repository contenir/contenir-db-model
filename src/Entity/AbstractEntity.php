<?php

namespace Contenir\Db\Model\Entity;

use Contenir\Db\Model\Exception\RuntimeException;
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
    protected $primaryKeys = [];

    /**
     * List of table columns
     *
     * @var array
     */
    protected $columns = [];

    /**
     * Table row data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Indicates if table row data has been modified programmatically
     *
     * @var array
     */
    protected $modifiedDataFields = [];

    /**
     * Lookup for table relations
     *
     * @var array
     */
    protected $relations = [];

    /**
     * EventsManager
     *
     * @var EventManager
     */
    protected $events;

    /**
     * Hydrator class for populating row
     *
     * @var string
     */
    protected $hydratorClass = EntityHydrator::class;

    public function __construct()
    {
        $columns = array_merge(
            array_values($this->columns),
            array_keys($this->relations)
        );

        $this->data               = array_fill_keys($columns, null);
        $this->modifiedDataFields = array_fill_keys($columns, false);
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
     * @param  string $columnName The user-specified column name.
     * @return string             The corresponding column value.
     * @throws Zend_Db_Table_Row_Exception if the $columnName is not a column in the row.
     */
    public function __get($columnName)
    {
        if (array_key_exists($columnName, $this->relations)) {
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
     * @param  string $columnName The column key.
     * @param  mixed  $value      The value for the property.
     * @return void
     * @throws Zend_Db_Table_Row_Exception
     */
    public function __set($columnName, $value)
    {
        if (array_key_exists($columnName, $this->data)) {
            $this->modifiedDataFields[$columnName] = ($this->data[$columnName] !== $value);
            $this->data[$columnName]               = $value;
            return $this;
        }

        return $this;
    }

    /**
     * Unset row field value
     *
     * @param  string $columnName The column key.
     * @return Zend_Db_Table_Row_Abstract
     * @throws Zend_Db_Table_Row_Exception
     */
    public function __unset($columnName)
    {
        if (! array_key_exists($columnName, $this->columns)) {
            throw new \InvalidArgumentException("Specified column \"$columnName\" is not in the row");
        }

        unset($this->data[$columnName]);

        return $this;
    }

    /**
     * Test existence of row field
     *
     * @param  string  $columnName   The column key.
     * @return boolean
     */
    public function __isset($columnName)
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
     * Populate Data
     *
     * @param  array $rowData
     * @param  bool  $rowExistsInDatabase
     * @return self Provides a fluent interface
     */
    public function populate(iterable $rowData): AbstractEntity
    {
        foreach ($rowData as $key => $value) {
            if ($this->__isset($key)) {
                $this->__set($key, $value);
            }
        }

        return $this;
    }

    /**
     * @param mixed $array
     * @return self Provides a fluent interface
     */
    public function exchangeArray($array): AbstractEntity
    {
        return $this->populate($array, true);
    }

    /**
     * Return a copy of the row array
     *
     * @return array
     */
    public function getArrayCopy()
    {
        return $this->data;
    }

    /**
     * Return a copy of the row array only for modified columns
     *
     * @return array
     */
    public function getModifiedArrayCopy()
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
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Return a new instance of the row hydrator
     *
     * @return object
     */
    public function getHydrator(): object
    {
        return new $this->hydratorClass();
    }
}
