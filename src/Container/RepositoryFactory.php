<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Container;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ConfigurationException;
use Contenir\Db\Model\Repository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Throwable;

use function is_a;

/**
 * Builds custom repositories whose only constructor argument is the
 * EntityManager. Register each one as
 * `UserRepository::class => RepositoryFactory::class`.
 *
 * @api
 */
final readonly class RepositoryFactory
{
    /**
     * @return Repository<object>
     *
     * @throws ConfigurationException
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container, string $requestedName): Repository
    {
        if (! is_a($requestedName, Repository::class, allow_string: true)) {
            throw ConfigurationException::invalidService($requestedName, Repository::class, $requestedName);
        }

        $em = ServiceLocator::get($container, EntityManager::class, EntityManager::class);

        try {
            return (new ReflectionClass($requestedName))->newInstance($em);
        } catch (Throwable $e) {
            throw ConfigurationException::unbuildableRepository($requestedName, $e);
        }
    }
}
