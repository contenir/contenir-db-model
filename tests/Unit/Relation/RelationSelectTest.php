<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Relation;

use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Relation\RelationSelect;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use PhpDb\Sql\Predicate\In;
use PhpDb\Sql\Select;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelationSelect::class)]
#[Group('unit')]
final class RelationSelectTest extends TestCase
{
    #[Test]
    public function restrictsDirectRelationToOwnerKeysOnQualifiedTargetColumn(): void
    {
        $metadata = new AttributeMetadataFactory(new TypeRegistry());
        $owner    = $metadata->getMetadataFor(User::class);
        $target   = $metadata->getMetadataFor(Order::class);

        $select = RelationSelect::build(new Select($target->table), $target, $owner->getRelation('orders'), [[1], [2]]);

        $predicate = $select->getRawState('where')->getPredicates()[0][1];
        static::assertInstanceOf(In::class, $predicate);
        static::assertSame('orders.user_id', $predicate->getIdentifier()->getValue());
        static::assertSame([1, 2], $predicate->getValueSet()->getValue());
    }

    #[Test]
    public function restrictsJoinTableRelationToOwnerKeysOnQualifiedJoinColumn(): void
    {
        $metadata = new AttributeMetadataFactory(new TypeRegistry());
        $owner    = $metadata->getMetadataFor(User::class);
        $target   = $metadata->getMetadataFor(Tag::class);

        $select = RelationSelect::build(new Select($target->table), $target, $owner->getRelation('tags'), [[1]]);

        $predicate = $select->getRawState('where')->getPredicates()[0][1];
        static::assertInstanceOf(In::class, $predicate);
        static::assertSame('user_tag.user_id', $predicate->getIdentifier()->getValue());
        static::assertSame([1], $predicate->getValueSet()->getValue());
    }
}
