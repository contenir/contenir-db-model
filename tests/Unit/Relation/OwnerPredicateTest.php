<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Relation;

use Contenir\Db\Model\Relation\OwnerPredicate;
use PhpDb\Sql\Predicate\In;
use PhpDb\Sql\Predicate\PredicateSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OwnerPredicate::class)]
#[Group('unit')]
final class OwnerPredicateTest extends TestCase
{
    #[Test]
    public function compositeKeysMatchEveryColumnOfAnyOwner(): void
    {
        $predicate = OwnerPredicate::matching(['a', 'b'], [[1, 2], [3, 4]]);

        static::assertInstanceOf(PredicateSet::class, $predicate);
        static::assertCount(2, $predicate);
    }

    #[Test]
    public function singleColumnKeysUseInWithEachOwnerValue(): void
    {
        $predicate = OwnerPredicate::matching(['owner_id'], [[1], [2], [3]]);

        static::assertInstanceOf(In::class, $predicate);
        static::assertSame('owner_id', $predicate->getIdentifier()->getValue());
        static::assertSame([1, 2, 3], $predicate->getValueSet()->getValue());
    }
}
