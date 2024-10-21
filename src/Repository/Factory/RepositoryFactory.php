<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Contenir\Db\Model\Repository\Factory;

use Contenir\Db\Model\Repository\RepositoryLookup;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class RepositoryFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $config = $container->get('config')['model'];

        $dbAdapterClass = $config['adapter'] ?? null;
        $dbAdapter      = $container->get($dbAdapterClass);

        $entityClass = $this->getEntityClass($config, $requestedName);
        $entity      = $container->get($entityClass);

        $repositoryLookup = $container->get(RepositoryLookup::class);

        return new $requestedName(
            $dbAdapter,
            $entity,
            $repositoryLookup
        );
    }

    protected function getEntityClass($config, $requestedName)
    {
        $entityClass = $config['map'][$requestedName] ?? null;

        if ($entityClass === null) {
            $entityClass = str_replace('Repository', 'Entity', $requestedName);
        }

        return $entityClass;
    }
}
