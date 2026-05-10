<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use ContenirTest\Db\Model\TestAsset\UserEntity;

class CleanLoadTest extends AbstractIntegrationTestCase
{
    public function testFindOneReturnsEntityWithNoModifications(): void
    {
        /** @var UserEntity $user */
        $user = $this->users->findOne(['id' => 1]);

        $this->assertSame([], $user->getModifiedArrayCopy());
    }

    public function testFindReturnsEntitiesWithNoModifications(): void
    {
        foreach ($this->users->find() as $user) {
            $this->assertSame([], $user->getModifiedArrayCopy());
        }
    }

    public function testModifyingLoadedEntityReportsOnlyChangedFields(): void
    {
        /** @var UserEntity $user */
        $user       = $this->users->findOne(['id' => 1]);
        $user->name = 'Renamed';

        $modified = $user->getModifiedArrayCopy();

        $this->assertSame(['name' => 'Renamed'], $modified);
    }

    public function testSaveOnLoadedEntityIssuesUpdateForOnlyTheChangedColumns(): void
    {
        /** @var UserEntity $user */
        $user       = $this->users->findOne(['id' => 1]);
        $user->name = 'Renamed';

        // sanity: the entity reports just `name` as dirty
        $this->assertSame(['name' => 'Renamed'], $user->getModifiedArrayCopy());

        $this->users->save($user);

        $reloaded = $this->users->findOne(['id' => 1]);
        $this->assertSame('Renamed', $reloaded->name);
        $this->assertSame('alice@example.com', $reloaded->email);
    }

    public function testSaveLeavesEntityCleanAfterUpdate(): void
    {
        /** @var UserEntity $user */
        $user       = $this->users->findOne(['id' => 1]);
        $user->name = 'Renamed';

        $this->users->save($user);

        $this->assertSame([], $user->getModifiedArrayCopy());
    }

    public function testSaveLeavesEntityCleanAfterInsert(): void
    {
        $entity = $this->users->create([
            'email' => 'fresh@example.com',
            'name'  => 'Fresh',
        ]);

        $this->users->save($entity);

        $this->assertSame([], $entity->getModifiedArrayCopy());
        $this->assertNotNull($entity->id);
    }
}
