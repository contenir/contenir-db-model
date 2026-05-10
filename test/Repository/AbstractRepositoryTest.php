<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Repository;

use Contenir\Db\Model\Repository\RepositoryLookup;
use ContenirTest\Db\Model\TestAsset\TestEntity;
use ContenirTest\Db\Model\TestAsset\TestRepository;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Sql;
use Laminas\Hydrator\Aggregate\AggregateHydrator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class AbstractRepositoryTest extends TestCase
{
    private TestRepository $repository;

    protected function setUp(): void
    {
        $adapter = $this->getMockBuilder(Adapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $entity = new TestEntity();

        $container = $this->createMock(ContainerInterface::class);
        $lookup    = new RepositoryLookup($container);

        $this->repository = new TestRepository($adapter, $entity, $lookup);
    }

    public function testGetTableReturnsConfiguredTableName(): void
    {
        $this->assertSame('users', $this->repository->getTable());
    }

    public function testGetAdapterReturnsConstructorAdapter(): void
    {
        $this->assertInstanceOf(Adapter::class, $this->repository->getAdapter());
    }

    public function testGetSqlReturnsLaminasSqlInstance(): void
    {
        $this->assertInstanceOf(Sql\Sql::class, $this->repository->getSql());
    }

    public function testGetHydratorReturnsAggregateHydratorWithRelations(): void
    {
        $hydrator = $this->repository->getHydrator();

        $this->assertInstanceOf(AggregateHydrator::class, $hydrator);
    }

    public function testGetResultSetReturnsHydratingResultSet(): void
    {
        $resultSet = $this->repository->getResultSet();

        $this->assertInstanceOf(HydratingResultSet::class, $resultSet);
    }

    public function testSelectReturnsSqlSelectInstance(): void
    {
        $this->assertInstanceOf(Sql\Select::class, $this->repository->select());
    }

    public function testGetLastInsertValueReturnsNullBeforeInsert(): void
    {
        $this->assertNull($this->repository->getLastInsertValue());
    }

    public function testPrepareSelectAppliesWhereAndOrder(): void
    {
        $select = $this->repository->select();
        $this->repository->prepareSelect(
            $select,
            ['id' => 1],
            ['name DESC']
        );

        $rawState = $select->getRawState();

        $this->assertNotEmpty($rawState['where']);
        $this->assertNotEmpty($rawState['order']);
    }

    public function testPrepareSelectWithoutSelectInstanceCreatesOne(): void
    {
        $select = $this->repository->prepareSelect(null, ['id' => 1]);

        $this->assertInstanceOf(Sql\Select::class, $select);
        $this->assertNotEmpty($select->getRawState()['where']);
    }

    public function testPrepareSelectWithStringOrderWrapsItInExpression(): void
    {
        $select = $this->repository->prepareSelect(null, [], 'name ASC');

        $this->assertNotEmpty($select->getRawState()['order']);
    }

    public function testCreateReturnsEntityInstance(): void
    {
        $entity = $this->repository->create(['id' => 5, 'name' => 'Mick']);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame(5, $entity->id);
        $this->assertSame('Mick', $entity->name);
    }
}
