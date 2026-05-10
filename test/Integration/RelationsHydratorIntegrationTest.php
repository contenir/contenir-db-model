<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use ContenirTest\Db\Model\TestAsset\OrderEntity;
use ContenirTest\Db\Model\TestAsset\ProfileEntity;
use ContenirTest\Db\Model\TestAsset\TagEntity;
use ContenirTest\Db\Model\TestAsset\UserEntity;

use function array_map;
use function sort;

class RelationsHydratorIntegrationTest extends AbstractIntegrationTestCase
{
    public function testSingleRelationLoadsRelatedRow(): void
    {
        /** @var UserEntity $user */
        $user = $this->users->findOne(['id' => 1]);

        $profile = $user->profile;

        $this->assertInstanceOf(ProfileEntity::class, $profile);
        $this->assertSame('Alice bio', $profile->bio);
        $this->assertSame(1, $profile->user_id);
    }

    public function testManyRelationLoadsCollectionInDeclaredOrder(): void
    {
        /** @var UserEntity $user */
        $user = $this->users->findOne(['id' => 1]);

        $orders = $user->orders;

        $this->assertCount(2, $orders);
        $this->assertContainsOnlyInstancesOf(OrderEntity::class, $orders);
        // ordered by created_at DESC per the relation definition
        $this->assertSame(250, (int) $orders[0]->total);
        $this->assertSame(100, (int) $orders[1]->total);
    }

    public function testManyRelationViaJoinTableHydratesTagsForUser(): void
    {
        /** @var UserEntity $user */
        $user = $this->users->findOne(['id' => 1]);

        $tags = $user->tags;

        $this->assertCount(2, $tags);
        $this->assertContainsOnlyInstancesOf(TagEntity::class, $tags);

        $labels = array_map(static fn(TagEntity $t) => $t->label, $tags);
        sort($labels);
        $this->assertSame(['beta', 'vip'], $labels);
    }

    public function testManyRelationViaJoinTableScopesByOwningRow(): void
    {
        /** @var UserEntity $user */
        $user = $this->users->findOne(['id' => 2]);

        $tags = $user->tags;

        $this->assertCount(1, $tags);
        $this->assertSame('staff', $tags[0]->label);
    }

    public function testRelationIsNotReloadedOnSubsequentAccess(): void
    {
        /** @var UserEntity $user */
        $user = $this->users->findOne(['id' => 1]);

        $first  = $user->profile;
        $second = $user->profile;

        $this->assertSame($first, $second);
    }
}
