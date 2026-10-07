<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Identity;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Hydrator\ChangeTracker;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
use Contenir\Db\Model\Identity\EntityLoader;
use Contenir\Db\Model\Identity\IdentifierResolver;
use Contenir\Db\Model\Identity\IdentityMap;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Relation\RelationInitializer;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The loader's collaborators are final in-memory components whose state is
 * the behaviour under test, so real instances are used and inspected.
 */
#[CoversClass(EntityLoader::class)]
#[Group('unit')]
final class EntityLoaderTest extends TestCase
{
    private EntityLoader $loader;

    private ChangeTracker $tracker;

    private IdentityMap $identityMap;

    /**
     * @var EntityMetadata<User>
     */
    private EntityMetadata $metadata;

    #[Test]
    public function inMemoryEditsWinOverReloadedRow(): void
    {
        $user        = $this->loader->load($this->metadata, ['id' => 1, 'email' => 'a@example.com']);
        $user->email = 'edited@example.com';

        $this->loader->load($this->metadata, ['id' => 1, 'email' => 'stored@example.com']);

        static::assertSame(['email' => 'edited@example.com'], $this->tracker->changes($this->metadata, $user));
    }

    #[Test]
    public function preparesRelationPropertiesOnNewlyLoadedEntity(): void
    {
        $user = $this->loader->load($this->metadata, ['id' => 1, 'email' => 'a@example.com']);

        static::assertInstanceOf(Collection::class, $user->orders);
    }

    #[Test]
    public function registersAndSnapshotsNewlyLoadedEntity(): void
    {
        $user = $this->loader->load($this->metadata, ['id' => 1, 'email' => 'a@example.com']);

        static::assertSame(
            [true, true, []],
            [
                $this->identityMap->contains($user),
                $this->tracker->isTracked($user),
                $this->tracker->changes($this->metadata, $user),
            ],
        );
    }

    #[Test]
    public function returnsDistinctInstancesForDistinctIdentifiers(): void
    {
        $first  = $this->loader->load($this->metadata, ['id' => 1, 'email' => 'a@example.com']);
        $second = $this->loader->load($this->metadata, ['id' => 2, 'email' => 'b@example.com']);

        static::assertNotSame($first, $second);
    }

    #[Test]
    public function returnsSameInstanceForSameIdentifier(): void
    {
        $first = $this->loader->load($this->metadata, ['id' => 1, 'email' => 'a@example.com']);

        static::assertSame($first, $this->loader->load($this->metadata, ['id' => '1', 'email' => 'a@example.com']));
    }

    protected function setUp(): void
    {
        $types             = TypeRegistry::withDefaults();
        $hydrator          = new EntityHydrator($types, new PropertyAccessor());
        $this->tracker     = new ChangeTracker($hydrator);
        $this->identityMap = new IdentityMap();
        $this->loader      = new EntityLoader(
            $hydrator,
            $this->tracker,
            $this->identityMap,
            new IdentifierResolver($types, new PropertyAccessor()),
            new RelationInitializer(new PropertyAccessor()),
        );
        $this->metadata = (new AttributeMetadataFactory())->getMetadataFor(User::class);
    }
}
