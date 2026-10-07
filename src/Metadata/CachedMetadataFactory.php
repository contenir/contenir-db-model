<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use DateInterval;
use Override;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

use function array_key_exists;
use function md5;

/**
 * Decorates another metadata factory with a PSR-16 cache so attribute
 * reflection happens once per deployment rather than once per process.
 *
 * Results are also memoised in-process, so each class is read from the
 * cache at most once per instance. The cache is treated as best-effort:
 * a failing backend, or an entry that does not hold metadata for the
 * requested class, is a miss and metadata is rebuilt from the inner
 * factory. A key the backend rejects (PSR-16 `InvalidArgumentException`)
 * is a configuration error and propagates. Entries are not invalidated
 * when entity classes change; clear the cache on deploy, or omit this
 * decorator in development.
 *
 * @api
 */
final class CachedMetadataFactory implements MetadataFactoryInterface
{
    /**
     * Bumped whenever the serialised shape of {@see EntityMetadata} changes
     * so stale entries written by an older release are never read back.
     */
    public const string KEY_PREFIX = 'contenir_db-model_metadata_v2_';

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
     * The key is the prefix plus the md5 of the class name. Class names
     * contain backslashes, which PSR-16 reserves, so they are hashed. The
     * result is 32 hex digits, within the default key pattern of strict
     * backends such as laminas-cache.
     *
     * @param class-string $className
     */
    public static function keyFor(string $className): string
    {
        return self::KEY_PREFIX . md5($className);
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
     * @throws InvalidArgumentException
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
     * @throws InvalidArgumentException
     */
    private function build(string $key, string $className): EntityMetadata
    {
        $metadata = $this->inner->getMetadataFor($className);
        $this->store($key, $metadata);

        return $metadata;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return EntityMetadata<T>|null
     *
     * @throws InvalidArgumentException
     */
    private function fetch(string $key, string $className): ?EntityMetadata
    {
        try {
            return self::matching($this->cache->get($key), $className);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (CacheException) {
            return null;
        }
    }

    /**
     * Best-effort write: a failing backend leaves the entry uncached.
     *
     * @param EntityMetadata<object> $metadata
     *
     * @throws InvalidArgumentException
     *
     * @mago-expect lint:no-empty-catch-clause The cache is best-effort; a failed write only means a rebuild next time.
     */
    private function store(string $key, EntityMetadata $metadata): void
    {
        try {
            $this->cache->set($key, $metadata, $this->ttl);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (CacheException) {
            // The entry stays uncached; the built metadata is still returned.
        }
    }
}
