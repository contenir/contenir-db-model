<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Cache;

use DateInterval;
use Override;
use Psr\SimpleCache\CacheInterface;
use Throwable;

use function array_key_exists;
use function is_int;
use function is_iterable;
use function is_string;
use function serialize;
use function unserialize;

/**
 * PSR-16 fake that serialises values like a real backend would, records
 * calls, and can be switched to fail on reads or writes.
 *
 * Parameters are declared `mixed` so the class satisfies psr/simple-cache
 * 1.x (untyped), 2.x and 3.x alike; keys are checked at runtime instead.
 */
final class InMemoryCache implements CacheInterface
{
    /**
     * @var array<string, string>
     */
    private array $values = [];

    public int $reads = 0;

    public int|DateInterval|null $lastTtl = null;

    public ?Throwable $readError = null;

    public ?Throwable $writeError = null;

    private static function key(mixed $key): string
    {
        if (! is_string($key)) {
            throw new FakeCacheException('key must be a string');
        }

        return $key;
    }

    /**
     * @return iterable<string>
     */
    private static function keys(mixed $keys): iterable
    {
        if (! is_iterable($keys)) {
            throw new FakeCacheException('keys must be iterable');
        }

        foreach ($keys as $key) {
            yield self::key($key);
        }
    }

    private static function ttl(mixed $ttl): int|DateInterval|null
    {
        if (null !== $ttl && ! is_int($ttl) && ! $ttl instanceof DateInterval) {
            throw new FakeCacheException('ttl must be null, an int or a DateInterval');
        }

        return $ttl;
    }

    #[Override]
    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    #[Override]
    public function delete(mixed $key): bool
    {
        unset($this->values[self::key($key)]);

        return true;
    }

    #[Override]
    public function deleteMultiple(mixed $keys): bool
    {
        foreach (self::keys($keys) as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[Override]
    public function get(mixed $key, mixed $default = null): mixed
    {
        $key = self::key($key);
        ++$this->reads;
        if ($this->readError instanceof Throwable) {
            throw $this->readError;
        }

        return array_key_exists($key, $this->values) ? unserialize($this->values[$key]) : $default;
    }

    #[Override]
    public function getMultiple(mixed $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach (self::keys($keys) as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    #[Override]
    public function has(mixed $key): bool
    {
        return array_key_exists(self::key($key), $this->values);
    }

    #[Override]
    public function set(mixed $key, mixed $value, mixed $ttl = null): bool
    {
        $key = self::key($key);
        $ttl = self::ttl($ttl);
        if ($this->writeError instanceof Throwable) {
            throw $this->writeError;
        }

        $this->values[$key] = serialize($value);
        $this->lastTtl      = $ttl;

        return true;
    }

    #[Override]
    public function setMultiple(mixed $values, mixed $ttl = null): bool
    {
        if (! is_iterable($values)) {
            throw new FakeCacheException('values must be iterable');
        }

        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }
}
