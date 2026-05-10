<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

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
            throw new class ('Service ' . $id . ' not registered') extends RuntimeException implements NotFoundExceptionInterface {
            };
        }

        return $this->services[$id];
    }
}
