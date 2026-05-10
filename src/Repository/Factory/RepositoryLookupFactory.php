<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 */

declare(strict_types=1);

namespace Contenir\Db\Model\Repository\Factory;

use Contenir\Db\Model\Repository\RepositoryLookup;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class RepositoryLookupFactory implements FactoryInterface
{
    /**
     * @param string     $requestedName
     * @param array|null $options
     * @return RepositoryLookup
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        return new $requestedName($container);
    }
}
