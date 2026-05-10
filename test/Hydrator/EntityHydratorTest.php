<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Hydrator;

use Contenir\Db\Model\Hydrator\EntityHydrator;
use ContenirTest\Db\Model\TestAsset\TestEntity;
use Laminas\Hydrator\ObjectPropertyHydrator;
use PHPUnit\Framework\TestCase;
use stdClass;

class EntityHydratorTest extends TestCase
{
    public function testHydrateRoutesAbstractEntityThroughSynch(): void
    {
        $hydrator = new EntityHydrator();
        $entity   = new TestEntity(['id' => 1, 'name' => 'old']);
        $entity->name = 'dirty';

        $hydrator->hydrate(['id' => 5, 'name' => 'fresh', 'email' => 'x@x'], $entity);

        $this->assertSame(5, $entity->id);
        $this->assertSame('fresh', $entity->name);
        $this->assertSame('x@x', $entity->email);
        $this->assertSame([], $entity->getModifiedArrayCopy());
    }

    public function testExtractReturnsEntityArrayCopy(): void
    {
        $hydrator = new EntityHydrator();
        $entity   = new TestEntity(['id' => 1, 'name' => 'A']);

        $extracted = $hydrator->extract($entity);

        $this->assertSame($entity->getArrayCopy(), $extracted);
    }

    public function testHydrateFallsBackToObjectPropertyHydratorForOtherObjects(): void
    {
        $hydrator = new EntityHydrator();
        $object   = new stdClass();

        $hydrator->hydrate(['foo' => 'bar'], $object);

        $this->assertSame('bar', $object->foo);
    }

    public function testIsAnObjectPropertyHydrator(): void
    {
        $this->assertInstanceOf(ObjectPropertyHydrator::class, new EntityHydrator());
    }
}
