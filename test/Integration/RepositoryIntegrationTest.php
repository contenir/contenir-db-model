<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use Contenir\Db\Model\Exception\InvalidArgumentException;
use Contenir\Db\Model\Repository\AbstractRepository;
use ContenirTest\Db\Model\TestAsset\OrderEntity;
use ContenirTest\Db\Model\TestAsset\UserEntity;
use Laminas\Db\Exception\RuntimeException as LaminasRuntimeException;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Insert;
use Laminas\Db\Sql\TableIdentifier;
use Laminas\Db\Sql\Update;
use ReflectionMethod;
use ReflectionProperty;

use function array_map;
use function iterator_to_array;

class RepositoryIntegrationTest extends AbstractIntegrationTestCase
{
    public function testFindReturnsHydratedResultSet(): void
    {
        $result = $this->users->find();

        $this->assertInstanceOf(ResultSetInterface::class, $result);
        $rows = iterator_to_array($result);

        $this->assertCount(2, $rows);
        $this->assertContainsOnlyInstancesOf(UserEntity::class, $rows);
    }

    public function testFindWithWhereFiltersRows(): void
    {
        $result = $this->users->find(['email' => 'bob@example.com']);

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertSame('Bob', $rows[0]->name);
    }

    public function testFindByFieldReturnsMatchingRows(): void
    {
        $result = $this->orders->findByField('user_id', 1);

        $rows = iterator_to_array($result);
        $this->assertCount(2, $rows);
    }

    public function testFindOneByFieldReturnsFirstMatch(): void
    {
        $entity = $this->users->findOneByField('email', 'alice@example.com');

        $this->assertInstanceOf(UserEntity::class, $entity);
        $this->assertSame(1, $entity->id);
    }

    public function testInsertPersistsRowAndReturnsLastInsertValue(): void
    {
        $affected = $this->users->insert([
            'email' => 'carol@example.com',
            'name'  => 'Carol',
        ]);

        $this->assertSame(1, $affected);
        $this->assertSame(3, $this->users->getLastInsertValue());

        $entity = $this->users->findOne(['id' => 3]);
        $this->assertSame('Carol', $entity->name);
    }

    public function testUpdateModifiesMatchingRows(): void
    {
        $affected = $this->users->update(['name' => 'Alicia'], ['id' => 1]);

        $this->assertSame(1, $affected);
        $this->assertSame('Alicia', $this->users->findOne(['id' => 1])->name);
    }

    public function testDeleteRemovesMatchingRows(): void
    {
        $affected = $this->orders->delete(['user_id' => 2]);

        $this->assertSame(1, $affected);
        $this->assertNull($this->orders->findOne(['user_id' => 2]));
    }

    public function testDeleteAcceptsClosure(): void
    {
        $affected = $this->orders->delete(static function ($delete): void {
            $delete->where(['user_id' => 1]);
        });

        $this->assertSame(2, $affected);
    }

    public function testSaveAutoInserts(): void
    {
        $entity = $this->users->create([
            'email' => 'dave@example.com',
            'name'  => 'Dave',
        ]);

        $this->users->save($entity);

        $this->assertNotNull($entity->id);
        $this->assertSame('Dave', $entity->name);
        $this->assertSame('Dave', $this->users->findOne(['id' => $entity->id])->name);
    }

    public function testSaveAutoUpdatesExistingRow(): void
    {
        /** @var UserEntity $entity */
        $entity       = $this->users->findOne(['id' => 1]);
        $entity->name = 'Alicia';

        $this->users->save($entity);

        $reloaded = $this->users->findOne(['id' => 1]);
        $this->assertSame('Alicia', $reloaded->name);
    }

    public function testSaveWithExplicitInsertModeForcesInsert(): void
    {
        $entity = $this->users->create([
            'email' => 'erin@example.com',
            'name'  => 'Erin',
        ]);

        $this->users->save($entity, AbstractRepository::MODE_INSERT);

        $this->assertSame('Erin', $this->users->findOne(['id' => $entity->id])->name);
    }

    public function testSynchRefreshesEntityFromDatabase(): void
    {
        /** @var UserEntity $entity */
        $entity = $this->users->findOne(['id' => 1]);

        $this->users->update(['name' => 'Updated'], ['id' => 1]);

        $this->users->synch($entity);

        $this->assertSame('Updated', $entity->name);
    }

    public function testSynchThrowsWhenRowNotFound(): void
    {
        $entity = $this->users->create(['email' => 'x@example.com', 'name' => 'X']);
        $entity->__set('id', 999);

        $this->expectException(LaminasRuntimeException::class);
        $this->users->synch($entity);
    }

    public function testInsertObjectMustMatchRepositoryTable(): void
    {
        $insert = new Insert('other_table');
        $insert->values(['email' => 'x', 'name' => 'X']);

        $reflection = new ReflectionMethod($this->users, 'executeInsert');
        $this->expectException(LaminasRuntimeException::class);
        $reflection->invoke($this->users, $insert);
    }

    public function testUpdateObjectMustMatchRepositoryTable(): void
    {
        $update = new Update('other_table');
        $update->set(['name' => 'X']);

        $reflection = new ReflectionMethod($this->users, 'executeUpdate');
        $this->expectException(LaminasRuntimeException::class);
        $reflection->invoke($this->users, $update);
    }

    public function testDeleteObjectMustMatchRepositoryTable(): void
    {
        $delete = new Delete('other_table');

        $reflection = new ReflectionMethod($this->users, 'executeDelete');
        $this->expectException(LaminasRuntimeException::class);
        $reflection->invoke($this->users, $delete);
    }

    public function testFindAppliesExpressionOrder(): void
    {
        $result = $this->orders->find([], ['total DESC']);

        $totals = [];
        foreach ($result as $order) {
            $totals[] = (int) $order->total;
        }

        $this->assertSame([250, 100, 75], $totals);
    }

    public function testInsertWithAliasedTableUnaliasesForExecution(): void
    {
        $aliasedRepository = $this->orders;

        $insert = new Insert(['o' => 'orders']);
        $insert->values([
            'user_id'    => 1,
            'total'      => 999,
            'created_at' => '2024-12-31',
        ]);

        // bypass the public insert() because it always uses the repository table.
        // Instead, point the Sql object's table to the aliased form to exercise
        // the unaliasing branch in executeInsert. We do this by reconfiguring
        // the repository's protected table via reflection.
        $tableProp = new ReflectionProperty($aliasedRepository, 'table');
        $tableProp->setValue($aliasedRepository, ['o' => 'orders']);

        try {
            $reflection = new ReflectionMethod($aliasedRepository, 'executeInsert');
            $affected   = $reflection->invoke($aliasedRepository, $insert);

            $this->assertSame(1, $affected);
        } finally {
            $tableProp->setValue($aliasedRepository, 'orders');
        }
    }

    public function testFindWithExpressionOrderString(): void
    {
        $result = $this->users->find([], 'name ASC');
        $names  = [];
        foreach ($result as $user) {
            $names[] = $user->name;
        }

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function testCreateProducesEntityFromArray(): void
    {
        $entity = $this->orders->create(['total' => 50, 'user_id' => 1]);

        $this->assertInstanceOf(OrderEntity::class, $entity);
        $this->assertSame(50, $entity->total);
    }

    public function testFindByFieldAcceptsArrayExtraConditions(): void
    {
        $result = $this->orders->findByField('user_id', 1, ['total > ?' => 200]);

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertSame(250, (int) $rows[0]->total);
    }

    public function testFindByFieldAcceptsClosureExtraConditions(): void
    {
        $result = $this->orders->findByField('user_id', 1, static function ($select): void {
            $select->where(['total > ?' => 200]);
        });

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertSame(250, (int) $rows[0]->total);
    }

    public function testFindByFieldAcceptsArrayValueAsInClause(): void
    {
        $result = $this->orders->findByField('user_id', [1, 2]);

        $rows = iterator_to_array($result);
        $this->assertCount(3, $rows);
    }

    public function testFindOneByFieldAcceptsArrayValueAndReturnsFirstMatch(): void
    {
        $entity = $this->users->findOneByField('id', [1, 2]);

        $this->assertNotNull($entity);
        $this->assertContains($entity->id, [1, 2]);
    }

    public function testRepositoryWhereDefaultIsApplied(): void
    {
        // exercise prepareSelect's $this->where branch
        $reflection = new ReflectionProperty($this->users, 'where');
        $reflection->setValue($this->users, ['id' => 1]);

        try {
            $rows = iterator_to_array($this->users->find());
            $this->assertCount(1, $rows);
            $this->assertSame(1, $rows[0]->id);
        } finally {
            $reflection->setValue($this->users, []);
        }
    }

    public function testRepositoryOrderDefaultIsApplied(): void
    {
        $reflection = new ReflectionProperty($this->users, 'order');
        $reflection->setValue($this->users, [new Expression('name ASC')]);

        try {
            $rows  = iterator_to_array($this->users->find());
            $names = array_map(static fn($u) => $u->name, $rows);
            $this->assertSame(['Alice', 'Bob'], $names);
        } finally {
            $reflection->setValue($this->users, []);
        }
    }

    public function testFindByFieldRejectsUnknownColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a known column');

        $this->users->findByField("id) OR (1=1 --", 1);
    }

    public function testFindOneByFieldRejectsUnknownColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->users->findOneByField('not_a_column', 1);
    }

    public function testFindByFieldRejectsRelationName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Relations are not table columns and shouldn't be addressable as
        // such even though they appear in the entity's data array.
        $this->users->findByField('profile', 1);
    }

    public function testFindAcceptsAssociativeOrderArray(): void
    {
        $rows  = iterator_to_array($this->users->find([], ['name' => 'DESC']));
        $names = array_map(static fn($u) => $u->name, $rows);

        $this->assertSame(['Bob', 'Alice'], $names);
    }

    public function testTableMatchGuardRecognisesAliasedArrayWhenTableIsString(): void
    {
        // $this->table = 'orders'; the Insert below carries ['o' => 'orders'].
        // The pre-fix code rejected this with the loose !=. Now it normalises
        // both sides and accepts the same underlying table.
        $insert = new Insert(['o' => 'orders']);
        $insert->values([
            'user_id'    => 1,
            'total'      => 999,
            'created_at' => '2024-12-31',
        ]);

        $reflection = new ReflectionMethod($this->orders, 'executeInsert');
        $affected   = $reflection->invoke($this->orders, $insert);

        $this->assertSame(1, $affected);
    }

    public function testTableMatchGuardRecognisesTableIdentifier(): void
    {
        // Reconfigure the repository to use a TableIdentifier and feed it an
        // Insert that carries the same table as a plain string. The guard
        // should treat them as the same table.
        $tableProp = new ReflectionProperty($this->orders, 'table');
        $tableProp->setValue($this->orders, new TableIdentifier('orders'));

        try {
            $insert = new Insert('orders');
            $insert->values([
                'user_id'    => 1,
                'total'      => 555,
                'created_at' => '2024-12-31',
            ]);

            $reflection = new ReflectionMethod($this->orders, 'executeInsert');
            $affected   = $reflection->invoke($this->orders, $insert);

            $this->assertSame(1, $affected);
        } finally {
            $tableProp->setValue($this->orders, 'orders');
        }
    }
}
