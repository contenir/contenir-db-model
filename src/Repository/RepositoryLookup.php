<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Repository;

use Psr\Container\ContainerInterface;

/**
 * Thin handle around a PSR-11 container used by {@see \Contenir\Db\Model\Hydrator\RelationsHydrator}
 * to resolve related repositories at relation-load time. A wrapper class is
 * used (rather than injecting the container directly) so the hydrator does
 * not need to be passed the entire container.
 */
class RepositoryLookup
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
