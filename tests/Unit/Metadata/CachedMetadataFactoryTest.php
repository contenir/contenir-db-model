<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Metadata;

use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\CachedMetadataFactory;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use ContenirTest\Db\Model\TestAsset\Cache\InMemoryCache;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use DateInterval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachedMetadataFactory::class)]
#[Group('unit')]
final class CachedMetadataFactoryTest extends TestCase
{
    private InMemoryCache $cache;

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableEntryProvider(): array
    {
        return [
            'not metadata'              => ['stale payload'],
            'metadata of another class' => [(new AttributeMetadataFactory())->getMetadataFor(Order::class)],
        ];
    }

    #[Test]
    public function keyReplacesNamespaceSeparatorsWithDots(): void
    {
        static::assertSame(
            'contenir.db-model.metadata.v1.ContenirTest.Db.Model.TestAsset.Entity.User',
            CachedMetadataFactory::keyFor(User::class),
        );
    }

    #[Test]
    public function passesConfiguredTtlToCache(): void
    {
        $ttl = new DateInterval('PT1H');

        (new CachedMetadataFactory(new AttributeMetadataFactory(), $this->cache, $ttl))->getMetadataFor(User::class);

        static::assertSame($ttl, $this->cache->lastTtl);
    }

    #[Test]
    public function readsEachClassFromCacheOncePerInstance(): void
    {
        $factory = new CachedMetadataFactory(new AttributeMetadataFactory(), $this->cache);
        $first   = $factory->getMetadataFor(User::class);

        static::assertSame([$first, 1], [$factory->getMetadataFor(User::class), $this->cache->reads]);
    }

    #[DataProvider('unusableEntryProvider')]
    #[Test]
    public function rebuildsWhenCachedEntryIsUnusable(mixed $entry): void
    {
        $this->cache->set(CachedMetadataFactory::keyFor(User::class), $entry);

        $metadata = (new CachedMetadataFactory(new AttributeMetadataFactory(), $this->cache))->getMetadataFor(
            User::class,
        );

        static::assertSame(User::class, $metadata->className);
    }

    #[Test]
    public function returnsBuiltMetadataWhenWriteFails(): void
    {
        $this->cache->failWrites = true;

        $metadata = (new CachedMetadataFactory(new AttributeMetadataFactory(), $this->cache))->getMetadataFor(
            User::class,
        );

        static::assertSame([User::class, false], [
            $metadata->className,
            $this->cache->has(CachedMetadataFactory::keyFor(User::class)),
        ]);
    }

    #[Test]
    public function servesCachedMetadataWithoutConsultingInnerFactory(): void
    {
        $expected = (new CachedMetadataFactory(new AttributeMetadataFactory(), $this->cache))->getMetadataFor(
            User::class,
        );

        $inner = $this->createMock(MetadataFactoryInterface::class);
        $inner->expects(static::never())->method('getMetadataFor');

        static::assertEquals($expected, (new CachedMetadataFactory($inner, $this->cache))->getMetadataFor(User::class));
    }

    #[Test]
    public function storesMetadataBuiltOnMiss(): void
    {
        $metadata = (new CachedMetadataFactory(new AttributeMetadataFactory(), $this->cache))->getMetadataFor(
            User::class,
        );

        static::assertEquals($metadata, $this->cache->get(CachedMetadataFactory::keyFor(User::class)));
    }

    #[Test]
    public function treatsFailingReadAsMiss(): void
    {
        $this->cache->failReads = true;

        $metadata = (new CachedMetadataFactory(new AttributeMetadataFactory(), $this->cache))->getMetadataFor(
            User::class,
        );

        static::assertSame(User::class, $metadata->className);
    }

    protected function setUp(): void
    {
        $this->cache = new InMemoryCache();
    }
}
