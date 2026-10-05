<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Cache;

use DateInterval;
use Override;
use Psr\SimpleCache\CacheInterface;

use function array_key_exists;
use function serialize;
use function unserialize;

/**
 * PSR-16 fake that serialises values like a real backend would, records
 * calls, and can be switched to fail on reads or writes.
 */
final class InMemoryCache implements CacheInterface
{
    /**
     * @var array<string, string>
     */
    private array $values = [];

    public int $reads = 0;

    public int|DateInterval|null $lastTtl = null;

    public bool $failReads = false;

    public bool $failWrites = false;

    #[Override]
    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    #[Override]
    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    #[Override]
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        ++$this->reads;
        if ($this->failReads) {
            throw new FakeCacheException('read failed');
        }

        return array_key_exists($key, $this->values) ? unserialize($this->values[$key]) : $default;
    }

    #[Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    #[Override]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    #[Override]
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        if ($this->failWrites) {
            throw new FakeCacheException('write failed');
        }

        $this->values[$key] = serialize($value);
        $this->lastTtl      = $ttl;

        return true;
    }

    #[Override]
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }
}
