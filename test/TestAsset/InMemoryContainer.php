<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Psr\Container\ContainerInterface;

use function array_key_exists;

class InMemoryContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $services = [];

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new InMemoryContainerNotFoundException('Service ' . $id . ' not registered');
        }

        return $this->services[$id];
    }
}
