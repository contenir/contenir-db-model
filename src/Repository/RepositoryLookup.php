<?php

namespace Contenir\Db\Model\Repository;

use Contenir\Db\Model\Entity\AbstractEntity;
use Psr\Container\ContainerInterface;

class RepositoryLookup
{
    /**
     * @var AbstractEntity
     */
    protected $entityPrototype = null;

    /**
     * @var
     */
    protected ?ContainerInterface $container;

    /**
     * @var AbstractEntity
     */
    protected $entityRelations = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }
}
