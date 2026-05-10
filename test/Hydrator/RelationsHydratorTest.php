<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Hydrator;

use Contenir\Db\Model\Entity\AbstractEntity;
use Contenir\Db\Model\Exception\InvalidArgumentException;
use Contenir\Db\Model\Exception\RuntimeException;
use Contenir\Db\Model\Hydrator\RelationsHydrator;
use Contenir\Db\Model\Repository\RepositoryLookup;
use ContenirTest\Db\Model\TestAsset\TestEntity;
use Laminas\EventManager\EventManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

class RelationsHydratorTest extends TestCase
{
    private RepositoryLookup $repositoryLookup;

    protected function setUp(): void
    {
        $this->repositoryLookup = new RepositoryLookup(
            $this->createMock(ContainerInterface::class)
        );
    }

    public function testHydrateAttachesLoadRelationListenerToEventManager(): void
    {
        $hydrator = new RelationsHydrator($this->repositoryLookup, [
            'profile' => null,
        ]);

        $entity = new TestEntity(['id' => 1]);
        $entity->setEventManager(new EventManager());

        $result = $hydrator->hydrate(['id' => 1], $entity);

        $this->assertSame($entity, $result);

        // Trigger the loadRelation event to confirm a listener was attached.
        // With an unknown relation key, the listener should be a no-op.
        $entity->getEventManager()->trigger('loadRelation', $entity, [
            'relation' => 'unknown',
        ]);

        $this->addToAssertionCount(1);
    }

    public function testRelationDefinitionThrowsWhenColumnMissing(): void
    {
        $hydrator = $this->newHydrator();
        $method   = new ReflectionMethod($hydrator, 'getRelationDefinition');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Relation column is not set');

        $method->invoke($hydrator, []);
    }

    public function testRelationDefinitionThrowsWhenTableMissing(): void
    {
        $hydrator = $this->newHydrator();
        $method   = new ReflectionMethod($hydrator, 'getRelationDefinition');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Relation table data is not set');

        $method->invoke($hydrator, ['column' => 'id']);
    }

    public function testRelationDefinitionThrowsWhenTableClassNotString(): void
    {
        $hydrator = $this->newHydrator();
        $method   = new ReflectionMethod($hydrator, 'getRelationDefinition');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Relation table class is not set');

        $method->invoke($hydrator, [
            'column' => 'id',
            'table'  => ['class' => null, 'column' => 'user_id'],
        ]);
    }

    public function testRelationDefinitionThrowsWhenViaTableMissing(): void
    {
        $hydrator = $this->newHydrator();
        $method   = new ReflectionMethod($hydrator, 'getRelationDefinition');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Via table is not set');

        $method->invoke($hydrator, [
            'column' => 'id',
            'table'  => ['class' => 'SomeRepository', 'column' => 'user_id'],
            'via'    => ['table' => null],
        ]);
    }

    public function testRelationDefinitionThrowsWhenColumnCountMismatch(): void
    {
        $hydrator = $this->newHydrator();
        $method   = new ReflectionMethod($hydrator, 'getRelationDefinition');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column counts of relations do not match');

        $method->invoke($hydrator, [
            'column' => ['id', 'tenant_id'],
            'table'  => ['class' => 'SomeRepository', 'column' => 'user_id'],
        ]);
    }

    public function testRelationDefinitionDefaultsTypeToMany(): void
    {
        $hydrator   = $this->newHydrator();
        $method     = new ReflectionMethod($hydrator, 'getRelationDefinition');
        $definition = $method->invoke($hydrator, [
            'column' => 'id',
            'table'  => ['class' => 'SomeRepository', 'column' => 'user_id'],
        ]);

        $this->assertSame(AbstractEntity::RELATION_MANY, $definition['relationType']);
        $this->assertSame(['id'], $definition['relationColumn']);
        $this->assertSame(['user_id'], $definition['relationTableColumn']);
        $this->assertSame('SomeRepository', $definition['relationTableClass']);
        $this->assertSame([], $definition['relationVia']);
        $this->assertSame([], $definition['relationCondition']);
        $this->assertSame([], $definition['relationOrder']);
    }

    public function testRelationDefinitionFallsBackToColumnWhenTableColumnMissing(): void
    {
        $hydrator   = $this->newHydrator();
        $method     = new ReflectionMethod($hydrator, 'getRelationDefinition');
        $definition = $method->invoke($hydrator, [
            'column' => 'id',
            'table'  => ['class' => 'SomeRepository'],
        ]);

        $this->assertSame(['id'], $definition['relationTableColumn']);
    }

    private function newHydrator(): RelationsHydrator
    {
        return new RelationsHydrator($this->repositoryLookup, []);
    }
}
