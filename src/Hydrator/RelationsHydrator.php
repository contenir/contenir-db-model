<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Hydrator;

use Contenir\Db\Model\Entity\AbstractEntity;
use Contenir\Db\Model\Exception\InvalidArgumentException;
use Contenir\Db\Model\Exception\RuntimeException;
use Contenir\Db\Model\Repository\RepositoryLookup;
use Laminas\Hydrator\ObjectPropertyHydrator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function array_combine;
use function array_key_exists;
use function array_keys;
use function count;
use function implode;
use function is_array;
use function is_string;
use function serialize;
use function sprintf;

/**
 * Lazy-loads entity relations on first access via a `loadRelation` event
 * listener attached during {@see self::hydrate()}. Identical FK lookups made
 * on the same hydrator instance are cached so iterating a result set in
 * which many parent rows share the same FK target only issues one query
 * per distinct lookup.
 */
class RelationsHydrator extends ObjectPropertyHydrator
{
    protected RepositoryLookup $repositoryLookup;
    protected array $relations;

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(RepositoryLookup $repositoryLookup, array $relations)
    {
        $this->repositoryLookup = $repositoryLookup;
        $this->relations        = $relations;
    }

    /**
     * @param array  $data
     * @param object $object
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function hydrate($data, $object): object
    {
        $object->getEventManager()->attach('loadRelation', function ($e) {
            $target = $e->getTarget();
            $params = $e->getParams();

            $relationName = $params['relation'];
            if (! array_key_exists($relationName, $this->relations)) {
                return;
            }

            $relationData = $target->getArrayCopy();
            $cacheKey     = $this->cacheKey($relationName, $relationData);

            if (! array_key_exists($cacheKey, $this->cache)) {
                $this->cache[$cacheKey] = $this->fetchRelation(
                    $this->relations[$relationName],
                    $relationData
                );
            }

            $target->{$relationName} = $this->cache[$cacheKey];
        });

        return $object;
    }

    /**
     * Drop any cached relation lookups. Call this after a write whose
     * effects should be visible on subsequent relation accesses.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Stable key for the (relation, fk-values) pair so duplicate lookups
     * on different rows that point at the same target hit the cache.
     */
    private function cacheKey(string $relationName, array $data): string
    {
        $columns = (array) ($this->relations[$relationName]['column'] ?? []);
        $values  = [];
        foreach ($columns as $column) {
            $values[$column] = $data[$column] ?? null;
        }

        return $relationName . '|' . serialize($values);
    }

    /**
     * @param array $relationConfig
     * @param array $data
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function fetchRelation(
        $relationConfig,
        $data = []
    ) {
        $relationDefinition  = $this->getRelationDefinition($relationConfig);
        $relationType        = $relationDefinition['relationType'];
        $relationColumn      = $relationDefinition['relationColumn'];
        $relationTableClass  = $relationDefinition['relationTableClass'];
        $relationTableColumn = $relationDefinition['relationTableColumn'];
        $relationCondition   = $relationDefinition['relationCondition'];
        $relationVia         = $relationDefinition['relationVia'];
        $relationOrder       = $relationDefinition['relationOrder'];

        $lookup       = array_combine($relationTableColumn, $relationColumn);
        $tableGateway = $this->repositoryLookup->getContainer()->get($relationTableClass);

        $rows   = [];
        $where  = [];
        $joinTo = null;

        if (! empty($relationVia)) {
            $joinFrom = $tableGateway->getTable();
            $joinTo   = $relationVia['table'];
            if (empty($relationVia['joinCondition'])) {
                $relationVia['joinCondition'] = [];
                foreach (array_keys($lookup) as $joinField) {
                    $joinFromField                  = $joinField;
                    $joinToField                    = $relationVia['join'] ?? $joinField;
                    $relationVia['joinCondition'][] = sprintf(
                        '%s.%s = %s.%s',
                        $joinFrom,
                        $joinFromField,
                        $joinTo,
                        $joinToField
                    );
                }
                if (is_array($relationVia['joinCondition'])) {
                    $relationVia['joinCondition'] = implode(' ', $relationVia['joinCondition']);
                }
            }
        }

        foreach ($lookup as $column => $value) {
            if (isset($data[$value])) {
                if (! empty($relationVia)) {
                    $matchViaColumn = $relationVia['column'] ?? $value;
                    $matchColumn    = sprintf('%s.%s', $joinTo, $matchViaColumn);
                } else {
                    $matchColumn = $column;
                }
                $where[$matchColumn] = $data[$value];
            }
        }

        foreach ($relationCondition as $key => $condition) {
            $where[$key] = $condition;
        }

        if (count($where)) {
            $select = $tableGateway->select();
            $select->where($where);

            if (count($relationVia)) {
                $select->join($relationVia['table'], $relationVia['joinCondition'], []);
            }

            $tableGateway->prepareSelect($select, null, $relationOrder);
            $results = $tableGateway->selectWith($select);

            switch ($relationType) {
                case AbstractEntity::RELATION_SINGLE:
                    $rows = $results->current();
                    // Mark as loaded-but-empty so accessing the relation
                    // again does not re-fire the load event.
                    if ($rows === null) {
                        $rows = false;
                    }
                    break;

                default:
                    foreach ($results as $row) {
                        $rows[] = $row;
                    }
                    break;
            }
        }

        return $rows;
    }

    protected function getRelationDefinition(array $relationDefinition): array
    {
        if (! isset($relationDefinition['column'])) {
            throw new InvalidArgumentException('Relation column is not set');
        }
        $relationColumn = (array) $relationDefinition['column'];

        if (! isset($relationDefinition['table'])) {
            throw new RuntimeException('Relation table data is not set');
        }
        $relationTable = $relationDefinition['table'];

        if (! is_string($relationTable['class'])) {
            throw new InvalidArgumentException('Relation table class is not set');
        }

        $relationType        = $relationDefinition['type'] ?? AbstractEntity::RELATION_MANY;
        $relationTableClass  = $relationTable['class'];
        $relationTableColumn = $relationTable['column'] ?? $relationColumn;
        $relationTableColumn = (array) $relationTableColumn;

        $where = $relationDefinition['where'] ?? [];
        $order = $relationDefinition['order'] ?? [];
        $via   = $relationDefinition['via'] ?? [];

        if (! empty($via)) {
            if (! is_array($via)) {
                throw new InvalidArgumentException('Relation "via" must be an array');
            }

            if (! isset($via['table']) || ! is_string($via['table'])) {
                throw new InvalidArgumentException('Via table is not set');
            }
        }

        if (count($relationColumn) !== count($relationTableColumn)) {
            throw new InvalidArgumentException('Column counts of relations do not match');
        }

        return [
            'relationType'        => $relationType,
            'relationColumn'      => $relationColumn,
            'relationTableClass'  => $relationTableClass,
            'relationTableColumn' => $relationTableColumn,
            'relationVia'         => $via === [] ? [] : (array) $via,
            'relationCondition'   => (array) $where,
            'relationOrder'       => (array) $order,
        ];
    }
}
