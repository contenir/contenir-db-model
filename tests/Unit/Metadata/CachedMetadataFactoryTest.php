<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Metadata;

use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\CachedMetadataFactory;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use ContenirTest\Db\Model\TestAsset\Cache\FakeCacheException;
use ContenirTest\Db\Model\TestAsset\Cache\FakeInvalidKeyException;
use ContenirTest\Db\Model\TestAsset\Cache\InMemoryCache;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use DateInterval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

use function md5;
use function str_replace;

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
    public function keyContainsOnlyCharactersAcceptedByStrictBackends(): void
    {
        static::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', CachedMetadataFactory::keyFor(User::class));
    }

    #[Test]
    public function keyIsPrefixPlusMd5OfClassName(): void
    {
        static::assertSame(
            'contenir_db-model_metadata_v2_' . md5(User::class),
            CachedMetadataFactory::keyFor(User::class),
        );
    }

    #[Test]
    public function keysDifferForClassNamesThatCollideWhenSanitised(): void
    {
        static::assertNotSame(
            CachedMetadataFactory::keyFor(User::class),
            CachedMetadataFactory::keyFor(str_replace('\\', replace: '_', subject: User::class)),
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
    public function propagatesInvalidKeyErrorFromRead(): void
    {
        $this->cache->readError = new FakeInvalidKeyException();

        $this->expectException(InvalidArgumentException::class);

        (new CachedMetadataFactory(new AttributeMetadataFactory(), $this->cache))->getMetadataFor(User::class);
    }

    #[Test]
    public function propagatesInvalidKeyErrorFromWrite(): void
    {
        $this->cache->writeError = new FakeInvalidKeyException();

        $this->expectException(InvalidArgumentException::class);

        (new CachedMetadataFactory(new AttributeMetadataFactory(), $this->cache))->getMetadataFor(User::class);
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
        $this->cache->writeError = new FakeCacheException();

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
        $this->cache->readError = new FakeCacheException();

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
