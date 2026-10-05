<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Container;

use Contenir\Db\Model\Exception\ConfigurationException;
use Contenir\Db\Model\Type\TypeConverterInterface;
use Contenir\Db\Model\Type\TypeRegistry;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the default type registry plus the converters listed under
 * "types", each fetched from the container by service name.
 *
 * @api
 */
final readonly class TypeRegistryFactory
{
    /**
     * @throws ConfigurationException
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TypeRegistry
    {
        $registry = TypeRegistry::withDefaults();
        foreach (ModuleConfig::from($container)->types as $name => $service) {
            $registry = $registry->withConverter(
                $name,
                ServiceLocator::get($container, $service, TypeConverterInterface::class),
            );
        }

        return $registry;
    }
}
