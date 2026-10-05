<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Relation;

use PhpDb\Sql\Predicate\In;
use PhpDb\Sql\Predicate\Operator;
use PhpDb\Sql\Predicate\PredicateInterface;
use PhpDb\Sql\Predicate\PredicateSet;

use function array_combine;
use function count;

/**
 * Restricts a relation select to a set of owner keys: IN for
 * single-column keys, (a = ? AND b = ?) OR ... for composite keys, which
 * every platform supports unlike tuple IN.
 *
 * @internal
 */
final readonly class OwnerPredicate
{
    /**
     * @param non-empty-list<string>            $columns
     * @param list<list<int|float|string|bool>> $ownerKeys each the same length as $columns
     */
    public static function matching(array $columns, array $ownerKeys): PredicateInterface
    {
        if (1 === count($columns)) {
            $values = [];
            foreach ($ownerKeys as $key) {
                $values[] = $key[0] ?? null;
            }

            return new In($columns[0], $values);
        }

        $any = new PredicateSet(defaultCombination: PredicateSet::OP_OR);
        foreach ($ownerKeys as $key) {
            $all = new PredicateSet();
            foreach (array_combine($columns, $key) as $column => $value) {
                $all->addPredicate(new Operator($column, Operator::OPERATOR_EQUAL_TO, $value));
            }

            $any->addPredicate($all);
        }

        return $any;
    }
}
