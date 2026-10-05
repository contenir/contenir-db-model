<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Container;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ConfigurationException;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Contenir\Db\Model\Type\TypeRegistry;
use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the EntityManager from the configured adapter service and the
 * metadata factory and type registry services.
 *
 * @api
 */
final readonly class EntityManagerFactory
{
    /**
     * @throws ConfigurationException
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): EntityManager
    {
        return new EntityManager(
            ServiceLocator::get($container, ModuleConfig::from($container)->adapter, AdapterInterface::class),
            ServiceLocator::get($container, MetadataFactoryInterface::class, MetadataFactoryInterface::class),
            ServiceLocator::get($container, TypeRegistry::class, TypeRegistry::class),
        );
    }
}
