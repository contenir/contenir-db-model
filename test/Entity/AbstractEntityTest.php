<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Entity;

use Contenir\Db\Model\Entity\AbstractEntity;
use Contenir\Db\Model\Exception\RuntimeException;
use ContenirTest\Db\Model\TestAsset\CollidingEntity;
use ContenirTest\Db\Model\TestAsset\CompositeKeyEntity;
use ContenirTest\Db\Model\TestAsset\TestEntity;
use InvalidArgumentException;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use PHPUnit\Framework\TestCase;

class AbstractEntityTest extends TestCase
{
    public function testConstructorPopulatesProvidedData(): void
    {
        $entity = new TestEntity([
            'id'    => 7,
            'name'  => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertSame(7, $entity->id);
        $this->assertSame('Alice', $entity->name);
        $this->assertSame('alice@example.com', $entity->email);
    }

    public function testConstructorWithNoDataInitializesNullColumns(): void
    {
        $entity = new TestEntity();
        $copy   = $entity->getArrayCopy();

        $this->assertArrayHasKey('id', $copy);
        $this->assertArrayHasKey('name', $copy);
        $this->assertArrayHasKey('email', $copy);
        $this->assertArrayHasKey('profile', $copy);
        $this->assertNull($copy['id']);
        $this->assertNull($copy['name']);
    }

    public function testGetPrimaryKeysReturnsKeyValues(): void
    {
        $entity = new TestEntity(['id' => 42, 'name' => 'Bob']);

        $this->assertSame(['id' => 42], $entity->getPrimaryKeys());
    }

    public function testGetPrimaryKeysSupportsCompositeKeys(): void
    {
        $entity = new CompositeKeyEntity([
            'tenant_id' => 1,
            'user_id'   => 2,
            'role'      => 'admin',
        ]);

        $this->assertSame(
            ['tenant_id' => 1, 'user_id' => 2],
            $entity->getPrimaryKeys()
        );
    }

    public function testGetPrimaryKeysReturnsExplicitNullForUnsetKey(): void
    {
        $entity = new TestEntity();

        // The PK key is always present in the result; an auto-increment id
        // that hasn't been assigned surfaces as null rather than vanishing
        // from the array.
        $this->assertSame(['id' => null], $entity->getPrimaryKeys());
    }

    public function testGetThrowsWhenColumnIsNotInRow(): void
    {
        $entity = new TestEntity();

        $this->expectException(RuntimeException::class);
        $entity->unknown;
    }

    public function testSetUpdatesValueAndMarksColumnModified(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Old']);

        $entity->name = 'New';

        $this->assertSame('New', $entity->name);
        $modified = $entity->getModifiedArrayCopy();
        $this->assertArrayHasKey('name', $modified);
        $this->assertSame('New', $modified['name']);
    }

    public function testSetIgnoresUnknownColumns(): void
    {
        $entity = new TestEntity();

        $entity->doesNotExist = 'value';

        $this->assertFalse(isset($entity->doesNotExist));
    }

    public function testSetSameValueClearsModifiedFlagForColumn(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Same']);

        $entity->name = 'Same';

        $this->assertArrayNotHasKey('name', $entity->getModifiedArrayCopy());
    }

    public function testIssetReturnsTrueForKnownColumns(): void
    {
        $entity = new TestEntity(['id' => 1]);

        $this->assertTrue(isset($entity->id));
        $this->assertTrue(isset($entity->name));
        $this->assertFalse(isset($entity->missing));
    }

    public function testUnsetRemovesColumnFromData(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'Test']);

        unset($entity->name);

        $this->assertFalse(isset($entity->name));
        $this->assertArrayNotHasKey('name', $entity->getArrayCopy());
        $this->assertArrayNotHasKey('name', $entity->getModifiedArrayCopy());
    }

    public function testUnsetThrowsForUnknownColumn(): void
    {
        $entity = new TestEntity();

        $this->expectException(InvalidArgumentException::class);
        unset($entity->missing);
    }

    public function testExchangeArrayRepopulatesData(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'A']);
        $entity->exchangeArray(['name' => 'B']);

        $this->assertSame('B', $entity->name);
        $this->assertSame(1, $entity->id);
    }

    public function testPopulateOnlyAcceptsKnownColumns(): void
    {
        $entity = new TestEntity();
        $entity->populate([
            'id'      => 9,
            'unknown' => 'ignored',
        ]);

        $this->assertSame(9, $entity->id);
        $this->assertFalse(isset($entity->unknown));
    }

    public function testSynchClearsExistingDataThenPopulates(): void
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'A', 'email' => 'a@example.com']);

        $entity->synch(['id' => 5, 'name' => 'fresh']);

        $this->assertSame(5, $entity->id);
        $this->assertSame('fresh', $entity->name);
        // email was reset to null because synch resets state before populating
        $this->assertNull($entity->email);
    }

    public function testSynchLeavesEntityWithNoModifications(): void
    {
        $entity        = new TestEntity(['id' => 1, 'name' => 'A']);
        $entity->name  = 'dirty';
        $this->assertNotSame([], $entity->getModifiedArrayCopy());

        $entity->synch(['id' => 5, 'name' => 'fresh']);

        $this->assertSame([], $entity->getModifiedArrayCopy());
    }

    public function testMarkCleanDropsExistingModificationFlags(): void
    {
        $entity        = new TestEntity(['id' => 1, 'name' => 'A']);
        $entity->name  = 'dirty';

        $entity->markClean();

        $this->assertSame([], $entity->getModifiedArrayCopy());
        // values are preserved
        $this->assertSame('dirty', $entity->name);
    }

    public function testGetModifiedArrayCopyExcludesRelationKeys(): void
    {
        $entity       = new TestEntity(['id' => 1]);
        $entity->name = 'Bob';

        $modified = $entity->getModifiedArrayCopy();

        $this->assertArrayHasKey('name', $modified);
        $this->assertArrayNotHasKey('profile', $modified);
    }

    public function testGetRelationsReturnsRelationDefinition(): void
    {
        $entity    = new TestEntity();
        $relations = $entity->getRelations();

        $this->assertArrayHasKey('profile', $relations);
        $this->assertSame(AbstractEntity::RELATION_SINGLE, $relations['profile']['type']);
    }

    public function testAccessingNullRelationTriggersLoadRelationEvent(): void
    {
        $entity       = new TestEntity(['id' => 1]);
        $eventManager = new EventManager();
        $entity->setEventManager($eventManager);

        $triggered = [];
        $eventManager->attach('loadRelation', function ($event) use (&$triggered) {
            $params              = $event->getParams();
            $triggered[]         = $params['relation'];
            $event->getTarget()->profile = ['loaded' => true];
        });

        $profile = $entity->profile;

        $this->assertSame(['profile'], $triggered);
        $this->assertSame(['loaded' => true], $profile);
    }

    public function testSleepReturnsSerializableProperties(): void
    {
        $entity     = new TestEntity(['id' => 1, 'name' => 'A']);
        $properties = $entity->__sleep();

        $this->assertSame(
            ['primaryKeys', 'columns', 'relations', 'data', 'modifiedDataFields'],
            $properties
        );
    }

    public function testSerializeAndUnserializePreservesData(): void
    {
        $entity         = new TestEntity(['id' => 1, 'name' => 'A']);
        $entity->email  = 'a@example.com';
        $serialized     = serialize($entity);
        /** @var TestEntity $restored */
        $restored = unserialize($serialized);

        $this->assertSame(1, $restored->id);
        $this->assertSame('A', $restored->name);
        $this->assertSame('a@example.com', $restored->email);
    }

    public function testSerializeAndUnserializePreservesRelations(): void
    {
        $entity = new TestEntity(['id' => 1]);

        $restored = unserialize(serialize($entity));

        $this->assertArrayHasKey('profile', $restored->getRelations());
    }

    public function testGetEventManagerLazyInitializesWhenNoneAttached(): void
    {
        $entity = new TestEntity(['id' => 1]);

        $eventManager = $entity->getEventManager();

        $this->assertInstanceOf(EventManagerInterface::class, $eventManager);
    }

    public function testAccessingRelationOnFreshEntityDoesNotThrow(): void
    {
        $entity = new TestEntity(['id' => 1]);

        // No event manager attached and no listeners; the auto-attached
        // EventManager has no loadRelation listeners so the relation stays
        // null but the call must not blow up.
        $this->assertNull($entity->profile);
    }

    public function testConstructorThrowsWhenColumnAndRelationShareAName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columns and relations must not share names');

        new CollidingEntity();
    }
}
