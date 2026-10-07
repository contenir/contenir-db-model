<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Identity;

use Contenir\Db\Model\Exception\IdentityConflictException;
use WeakMap;

use function count;
use function serialize;

/**
 * Holds one instance per (entity class, primary key), so loading the same
 * row twice yields the same object.
 *
 * Identifiers are database-form primary-key values keyed by column, as
 * produced by {@see IdentifierResolver}. Entities are held strongly:
 * long-running processes must call {@see self::clear()} between units of
 * work or the map grows without bound.
 *
 * @internal
 */
final class IdentityMap
{
    /**
     * @var array<string, object>
     */
    private array $entities = [];

    /**
     * @var WeakMap<object, array{class: class-string, key: string, identifier: array<string, int|float|string|bool>}>
     */
    private WeakMap $index;

    public function __construct()
    {
        $this->index = new WeakMap();
    }

    /**
     * serialize() keeps scalar types distinct and never fails on binary
     * key values, unlike json_encode().
     *
     * @param array<string, int|float|string|bool> $identifier
     */
    private static function key(string $className, array $identifier): string
    {
        return serialize([$className, $identifier]);
    }

    /**
     * Register $entity under its identifier. Re-adding the same object is
     * a no-op; an object already registered under another identifier is
     * moved (e.g. after a primary-key update).
     *
     * @param class-string                         $className
     * @param array<string, int|float|string|bool> $identifier
     *
     * @throws IdentityConflictException When a different object holds the identifier.
     */
    public function add(string $className, array $identifier, object $entity): void
    {
        $key      = self::key($className, $identifier);
        $existing = $this->entities[$key] ?? null;
        if (null !== $existing && $existing !== $entity) {
            throw IdentityConflictException::alreadyManaged($className, $identifier);
        }

        $this->remove($entity);
        $this->entities[$key] = $entity;
        $this->index[$entity] = ['class' => $className, 'key' => $key, 'identifier' => $identifier];
    }

    public function clear(): void
    {
        $this->entities = [];
        $this->index    = new WeakMap();
    }

    public function contains(object $entity): bool
    {
        return $this->index->offsetExists($entity);
    }

    public function count(): int
    {
        return count($this->entities);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>                          $className
     * @param array<string, int|float|string|bool>     $identifier
     *
     * @return T|null
     */
    public function get(string $className, array $identifier): ?object
    {
        /** @var T|null */
        return $this->entities[self::key($className, $identifier)] ?? null;
    }

    /**
     * @return array<string, int|float|string|bool>|null
     */
    public function identifierOf(object $entity): ?array
    {
        return ($this->index[$entity] ?? null)['identifier'] ?? null;
    }

    public function remove(object $entity): void
    {
        $entry = $this->index[$entity] ?? null;
        if (null === $entry) {
            return;
        }

        unset($this->entities[$entry['key']], $this->index[$entity]);
    }
}
