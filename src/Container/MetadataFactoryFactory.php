<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Container;

use Contenir\Db\Model\Exception\ConfigurationException;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\CachedMetadataFactory;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Builds the attribute metadata factory, wrapped in
 * {@see CachedMetadataFactory} when "metadata_cache" names a PSR-16 cache
 * service.
 *
 * @api
 */
final readonly class MetadataFactoryFactory
{
    /**
     * @throws ConfigurationException
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): MetadataFactoryInterface
    {
        $config  = ModuleConfig::from($container);
        $factory = new AttributeMetadataFactory();
        if (null === $config->metadataCache) {
            return $factory;
        }

        return new CachedMetadataFactory(
            $factory,
            ServiceLocator::get($container, $config->metadataCache, CacheInterface::class),
            $config->metadataCacheTtl,
        );
    }
}
