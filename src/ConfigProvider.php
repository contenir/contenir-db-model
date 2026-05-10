<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

use Laminas\Db\Adapter\Adapter;

class ConfigProvider
{
    /**
     * Retrieve default laminas-paginator configuration.
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'model'        => $this->getDbModelConfig(),
        ];
    }

    /**
     * Retrieve dependency configuration for laminas-paginator.
     */
    public function getDependencyConfig(): array
    {
        return [
            'aliases'   => [],
            'factories' => [
                Repository\RepositoryLookup::class => Repository\Factory\RepositoryLookupFactory::class,
            ],
        ];
    }

    /**
     * Provide default route plugin manager configuration.
     */
    public function getDbModelConfig(): array
    {
        return [
            'adapter' => Adapter::class,
        ];
    }
}
