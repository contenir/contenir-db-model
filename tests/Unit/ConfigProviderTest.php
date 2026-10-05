<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit;

use Contenir\Db\Model\ConfigProvider;
use Contenir\Db\Model\Container\EntityManagerFactory;
use Contenir\Db\Model\Container\MetadataFactoryFactory;
use Contenir\Db\Model\Container\TypeRegistryFactory;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Contenir\Db\Model\Type\TypeRegistry;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigProvider::class)]
#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function providesDefaultModuleConfiguration(): void
    {
        $config = (new ConfigProvider())();

        static::assertSame(
            [
                'adapter'            => AdapterInterface::class,
                'metadata_cache'     => null,
                'metadata_cache_ttl' => null,
                'types'              => [],
            ],
            $config['contenir_db_model'],
        );
    }

    #[Test]
    public function registersFactoriesForPublicServices(): void
    {
        static::assertSame(
            [
                'factories' => [
                    EntityManager::class            => EntityManagerFactory::class,
                    MetadataFactoryInterface::class => MetadataFactoryFactory::class,
                    TypeRegistry::class             => TypeRegistryFactory::class,
                ],
            ],
            (new ConfigProvider())->getDependencies(),
        );
    }
}
