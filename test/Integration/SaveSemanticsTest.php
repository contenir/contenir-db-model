<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use Contenir\Db\Model\Repository\AbstractRepository;
use ContenirTest\Db\Model\TestAsset\UserEntity;
use Laminas\Db\Adapter\Profiler\Profiler;

use function count;

class SaveSemanticsTest extends AbstractIntegrationTestCase
{
    public function testDefaultSaveDoesNotIssueRefreshSelect(): void
    {
        $profiler = new Profiler();
        $this->adapter->getDriver()->setProfiler($profiler);

        $entity = $this->users->create([
            'email' => 'fresh@example.com',
            'name'  => 'Fresh',
        ]);

        $before = count($profiler->getProfiles());
        $this->users->save($entity);
        $after = count($profiler->getProfiles());

        // One INSERT, no follow-up SELECT. (Pre-v2 behaviour was insert
        // + select to refresh the entity from storage.)
        $this->assertSame(1, $after - $before);
    }

    public function testSaveWithRefreshTrueIssuesPostWriteSelect(): void
    {
        $profiler = new Profiler();
        $this->adapter->getDriver()->setProfiler($profiler);

        $entity = $this->users->create([
            'email' => 'fresh@example.com',
            'name'  => 'Fresh',
        ]);

        $before = count($profiler->getProfiles());
        $this->users->save($entity, AbstractRepository::MODE_AUTO, refresh: true);
        $after = count($profiler->getProfiles());

        // INSERT + refresh SELECT.
        $this->assertSame(2, $after - $before);
    }

    public function testDefaultSaveStillBackfillsAutoIncrementPk(): void
    {
        $entity = $this->users->create([
            'email' => 'fresh@example.com',
            'name'  => 'Fresh',
        ]);

        $this->users->save($entity);

        $this->assertNotNull($entity->id);
        $this->assertSame([], $entity->getModifiedArrayCopy());
    }

    public function testDefaultSaveOnUpdateLeavesEntityClean(): void
    {
        /** @var UserEntity $user */
        $user       = $this->users->findOne(['id' => 1]);
        $user->name = 'Renamed';

        $this->users->save($user);

        $this->assertSame([], $user->getModifiedArrayCopy());
    }

    public function testRefreshTrueLeavesConnectionInACleanState(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $this->assertFalse($connection->inTransaction());

        $this->users->save(
            $this->users->create(['email' => 'tx@example.com', 'name' => 'Tx']),
            AbstractRepository::MODE_AUTO,
            refresh: true
        );

        // The save() refresh wrapped its INSERT + SELECT in a transaction
        // and committed; we should be back outside any transaction.
        $this->assertFalse($connection->inTransaction());
    }
}
