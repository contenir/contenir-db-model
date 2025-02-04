<?php

namespace Contenir\Db\Model\Repository;

use Contenir\Db\Model\Entity\AbstractEntity;
use Psr\Container\ContainerInterface;

class RepositoryLookup
{
    /**
     * @var AbstractEntity|null
     */
    protected ?AbstractEntity $entityPrototype = null;

    /**
     * @var ContainerInterface|null
     */
    protected ?ContainerInterface $container;

    /**
     * @var array
     */
    protected array $entityRelations = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }
}
