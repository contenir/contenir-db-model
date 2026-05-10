<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use Contenir\Db\Model\Exception\InvalidArgumentException;
use ContenirTest\Db\Model\TestAsset\OrderEntity;
use ContenirTest\Db\Model\TestAsset\UserEntity;
use Laminas\Db\Adapter\Profiler\Profiler;
use Laminas\Db\Exception\RuntimeException;

use function count;
use function iterator_to_array;

class PreloadRelationsTest extends AbstractIntegrationTestCase
{
    public function testPreloadAssignsManyRelationToEachParent(): void
    {
        /** @var UserEntity[] $users */
        $users = iterator_to_array($this->users->find());

        $this->users->preloadRelations($users, ['orders']);

        $this->assertCount(2, $users[0]->orders);
        $this->assertCount(1, $users[1]->orders);
        $this->assertContainsOnlyInstancesOf(OrderEntity::class, $users[0]->orders);
    }

    public function testPreloadOnEmptyParentSetIsANoop(): void
    {
        // Should not throw and should not query
        $this->users->preloadRelations([], ['orders']);

        $this->addToAssertionCount(1);
    }

    public function testPreloadAssignsSingleRelation(): void
    {
        /** @var UserEntity[] $users */
        $users = iterator_to_array($this->users->find());

        $this->users->preloadRelations($users, ['profile']);

        $this->assertSame('Alice bio', $users[0]->profile->bio);
        $this->assertSame('Bob bio', $users[1]->profile->bio);
    }

    public function testPreloadIssuesOneSelectRegardlessOfParentCount(): void
    {
        $profiler = new Profiler();
        $this->adapter->getDriver()->setProfiler($profiler);

        $users = iterator_to_array($this->users->find());
        $profiler->profilerStart('marker');
        $profiler->profilerFinish();
        $beforeCount = count($profiler->getProfiles());

        $this->users->preloadRelations($users, ['orders']);

        // touch each user's relation; should not emit further queries
        foreach ($users as $user) {
            $count = count($user->orders);
            $this->assertGreaterThanOrEqual(0, $count);
        }

        $afterCount = count($profiler->getProfiles());

        // Exactly one query for the preload, none afterwards
        $this->assertSame(1, $afterCount - $beforeCount);
    }

    public function testPreloadRejectsUnknownRelationName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a declared relation');

        $users = iterator_to_array($this->users->find());
        $this->users->preloadRelations($users, ['no_such_relation']);
    }

    public function testPreloadRejectsViaRelations(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not yet support "via"');

        $users = iterator_to_array($this->users->find());
        $this->users->preloadRelations($users, ['tags']);
    }

    public function testPreloadAssignsEmptyArrayWhenNoChildrenMatch(): void
    {
        // delete all orders so preload has nothing to find
        $this->orders->delete(['1=1']);

        $users = iterator_to_array($this->users->find());

        $this->users->preloadRelations($users, ['orders']);

        $this->assertSame([], $users[0]->orders);
        $this->assertSame([], $users[1]->orders);
    }

    public function testPreloadedRelationDoesNotReloadOnAccess(): void
    {
        $users = iterator_to_array($this->users->find());

        $this->users->preloadRelations($users, ['profile']);

        $first  = $users[0]->profile;
        $second = $users[0]->profile;

        $this->assertSame($first, $second);
    }
}
