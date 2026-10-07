<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Relation;

use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\JoinTable;
use Contenir\Db\Model\Metadata\RelationMetadata;
use PhpDb\Sql\Select;

use function array_combine;
use function implode;

/**
 * Builds the select that loads a relation's targets for a set of owner
 * key tuples. Join-table relations select the join's owner columns under
 * {@see self::OWNER_ALIAS} aliases so rows can be grouped back to owners,
 * and order by the join table's columns before the target's.
 *
 * @internal
 */
final readonly class RelationSelect
{
    public const string OWNER_ALIAS = '__owner_';

    /**
     * @template T of object
     *
     * @param EntityMetadata<T>                 $target
     * @param list<list<int|float|string|bool>> $ownerKeys
     */
    public static function build(
        Select $select,
        EntityMetadata $target,
        RelationMetadata $relation,
        array $ownerKeys,
    ): Select {
        $join  = $relation->keys->joinTable;
        $match = null === $join
            ? self::qualify($target->table, $relation->keys->targetColumns)
            : self::join($select, $target, $relation, $join);

        if ([] !== $match) {
            $select->where(OwnerPredicate::matching($match, $ownerKeys));
        }

        foreach ($relation->criteria->where as $column => $value) {
            $select->where(["{$target->table}.{$column}" => $value]);
        }

        foreach ($relation->criteria->orderBy as $column => $direction) {
            $select->order(["{$target->table}.{$column}" => $direction]);
        }

        return $select;
    }

    /**
     * Joins the join table and orders by its columns, ahead of any target
     * ordering added afterwards.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $target
     *
     * @return list<string> qualified join-table columns matching the owner key
     */
    private static function join(
        Select $select,
        EntityMetadata $target,
        RelationMetadata $relation,
        JoinTable $join,
    ): array {
        $on = [];
        foreach (array_combine($join->targetColumns, $relation->keys->targetColumns) as $joinColumn => $targetColumn) {
            $on[] = "{$join->table}.{$joinColumn} = {$target->table}.{$targetColumn}";
        }

        $aliases = [];
        foreach ($join->localColumns as $i => $column) {
            $aliases[self::OWNER_ALIAS . $i] = $column;
        }

        $select->join($join->table, implode(' AND ', $on), $aliases);

        foreach ($join->orderBy as $column => $direction) {
            $select->order(["{$join->table}.{$column}" => $direction]);
        }

        return self::qualify($join->table, $join->localColumns);
    }

    /**
     * @param list<string> $columns
     *
     * @return list<string>
     */
    private static function qualify(string $table, array $columns): array
    {
        $qualified = [];
        foreach ($columns as $column) {
            $qualified[] = "{$table}.{$column}";
        }

        return $qualified;
    }
}
