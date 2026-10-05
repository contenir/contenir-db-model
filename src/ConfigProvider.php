<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

use Contenir\Db\Model\Container\EntityManagerFactory;
use Contenir\Db\Model\Container\MetadataFactoryFactory;
use Contenir\Db\Model\Container\ModuleConfig;
use Contenir\Db\Model\Container\TypeRegistryFactory;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Contenir\Db\Model\Type\TypeRegistry;
use PhpDb\Adapter\AdapterInterface;

/**
 * Service wiring for PSR-11 containers via the Laminas component installer
 * or a Mezzio-style config aggregator.
 *
 * @api
 */
final readonly class ConfigProvider
{
    /**
     * @return array{factories: array<class-string, class-string>}
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                EntityManager::class            => EntityManagerFactory::class,
                MetadataFactoryInterface::class => MetadataFactoryFactory::class,
                TypeRegistry::class             => TypeRegistryFactory::class,
            ],
        ];
    }

    /**
     * @return array{
     *     dependencies: array{factories: array<class-string, class-string>},
     *     contenir_db_model: array{adapter: string, metadata_cache: null, metadata_cache_ttl: null, types: array<never, never>},
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies'    => $this->getDependencies(),
            ModuleConfig::KEY => [
                'adapter'            => AdapterInterface::class,
                'metadata_cache'     => null,
                'metadata_cache_ttl' => null,
                'types'              => [],
            ],
        ];
    }
}
