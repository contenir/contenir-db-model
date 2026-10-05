<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use DateInterval;
use Override;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;

use function array_key_exists;
use function strtr;

/**
 * Decorates another metadata factory with a PSR-16 cache so attribute
 * reflection happens once per deployment rather than once per process.
 *
 * Results are also memoised in-process, so each class is read from the
 * cache at most once per instance. The cache is treated as best-effort:
 * a failing backend, or an entry that does not hold metadata for the
 * requested class, is a miss and metadata is rebuilt from the inner
 * factory. Entries are not invalidated when entity classes change; clear
 * the cache on deploy, or omit this decorator in development.
 *
 * @api
 */
final class CachedMetadataFactory implements MetadataFactoryInterface
{
    /**
     * Bumped whenever the serialised shape of {@see EntityMetadata} changes
     * so stale entries written by an older release are never read back.
     */
    public const string KEY_PREFIX = 'contenir.db-model.metadata.v1.';

    /**
     * @var array<class-string, EntityMetadata<object>>
     */
    private array $loaded = [];

    public function __construct(
        private readonly MetadataFactoryInterface $inner,
        private readonly CacheInterface $cache,
        private readonly int|DateInterval|null $ttl = null,
    ) {}

    /**
     * PSR-16 reserves `{}()/\@:` in keys; class names can only contain the
     * backslash among those, so it is swapped for a dot.
     *
     * @param class-string $className
     */
    public static function keyFor(string $className): string
    {
        return self::KEY_PREFIX . strtr($className, ['\\' => '.']);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return EntityMetadata<T>|null
     */
    private static function matching(mixed $cached, string $className): ?EntityMetadata
    {
        if (! $cached instanceof EntityMetadata || $cached->className !== $className) {
            return null;
        }

        /** @var EntityMetadata<T> */
        return $cached;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return EntityMetadata<T>
     *
     * @throws MappingException
     */
    #[Override]
    public function getMetadataFor(string $className): EntityMetadata
    {
        if (array_key_exists($className, $this->loaded)) {
            /** @var EntityMetadata<T> */
            return $this->loaded[$className];
        }

        $key      = self::keyFor($className);
        $metadata = $this->fetch($key, $className) ?? $this->build($key, $className);

        return $this->loaded[$className] = $metadata;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return EntityMetadata<T>
     *
     * @throws MappingException
     */
    private function build(string $key, string $className): EntityMetadata
    {
        $metadata = $this->inner->getMetadataFor($className);

        try {
            $this->cache->set($key, $metadata, $this->ttl);
        } catch (CacheException) {
            return $metadata;
        }

        return $metadata;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return EntityMetadata<T>|null
     */
    private function fetch(string $key, string $className): ?EntityMetadata
    {
        try {
            return self::matching($this->cache->get($key), $className);
        } catch (CacheException) {
            return null;
        }
    }
}
