<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Container;

use Contenir\Db\Model\Exception\ConfigurationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Fetches services with a type check, so misconfiguration fails with a
 * clear message rather than a TypeError deep in construction.
 *
 * @internal
 */
final readonly class ServiceLocator
{
    /**
     * @template S of object
     *
     * @param class-string<S> $type
     *
     * @return S
     *
     * @throws ConfigurationException
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment Container services are untyped; the type is checked here.
     */
    public static function get(ContainerInterface $container, string $name, string $type): object
    {
        $service = $container->get($name);
        if (! $service instanceof $type) {
            throw ConfigurationException::invalidService($name, $type, $service);
        }

        return $service;
    }
}
