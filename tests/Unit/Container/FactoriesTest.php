<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Container;

use Contenir\Db\Model\Container\MetadataFactoryFactory;
use Contenir\Db\Model\Container\ServiceLocator;
use Contenir\Db\Model\Container\TypeRegistryFactory;
use Contenir\Db\Model\Exception\ConfigurationException;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\CachedMetadataFactory;
use Contenir\Db\Model\Type\TypeConverterInterface;
use ContenirTest\Db\Model\TestAsset\Cache\InMemoryCache;
use ContenirTest\Db\Model\TestAsset\Container\InMemoryContainer;
use ContenirTest\Db\Model\TestAsset\Factory\FieldFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(MetadataFactoryFactory::class)]
#[CoversClass(TypeRegistryFactory::class)]
#[CoversClass(ServiceLocator::class)]
#[CoversClass(ConfigurationException::class)]
#[Group('unit')]
final class FactoriesTest extends TestCase
{
    /**
     * @param array<string, mixed> $module
     * @param array<string, mixed> $services
     */
    private static function container(array $module, array $services = []): InMemoryContainer
    {
        return new InMemoryContainer(['config' => ['contenir_db_model' => $module]] + $services);
    }

    #[Test]
    public function metadataFactoryIsCachedWhenCacheServiceConfigured(): void
    {
        $factory = (new MetadataFactoryFactory())(self::container(['metadata_cache' => 'cache'], [
            'cache' => new InMemoryCache(),
        ]));

        static::assertInstanceOf(CachedMetadataFactory::class, $factory);
    }

    #[Test]
    public function metadataFactoryIsUncachedByDefault(): void
    {
        static::assertInstanceOf(AttributeMetadataFactory::class, (new MetadataFactoryFactory())(self::container([])));
    }

    #[Test]
    public function rejectsCacheServiceOfWrongType(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'Service "cache" must be an instance of Psr\SimpleCache\CacheInterface, got stdClass',
        );

        (new MetadataFactoryFactory())(self::container(['metadata_cache' => 'cache'], ['cache' => new stdClass()]));
    }

    #[Test]
    public function rejectsConverterServiceOfWrongType(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Service "converter.money" must be an instance of '
            . TypeConverterInterface::class);

        (new TypeRegistryFactory())(self::container(['types' => ['money' => 'converter.money']], [
            'converter.money' => 'x',
        ]));
    }

    #[Test]
    public function typeRegistryIncludesConfiguredConverterServices(): void
    {
        $converter = $this->createStub(TypeConverterInterface::class);
        $registry  = (new TypeRegistryFactory())(self::container(['types' => ['money' => 'converter.money']], [
            'converter.money' => $converter,
        ]));

        static::assertSame($converter, $registry->converterFor(FieldFactory::make('int', typeName: 'money')));
    }
}
