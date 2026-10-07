# Metadata caching

Building metadata means reflecting over every mapped property and
attribute. `AttributeMetadataFactory` does this once per class per process.
In PHP-FPM that is once per request. `CachedMetadataFactory` keeps the
result in any PSR-16 cache, so the reflection runs once per deployment
instead.

```php
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\CachedMetadataFactory;

$factory = new CachedMetadataFactory(
    new AttributeMetadataFactory(),
    $psr16Cache,          // Psr\SimpleCache\CacheInterface
    ttl: null,            // optional: int seconds or DateInterval; null = backend default
);

$factory->getMetadataFor(User::class);
```

Any PSR-16 implementation works. Examples are `laminas/laminas-cache` with a
`SimpleCacheDecorator`, `symfony/cache` via `Psr16Cache`, or a Redis/APCu
adapter. An APCu or PHP-file backend is a good fit, because the cached
values are small and read on every request.

## Behaviour

- **Two levels.** Each class is read from the PSR-16 cache at most once per
  `CachedMetadataFactory` instance. After that it is served from memory.
- **Keys.** Keys are `contenir_db-model_metadata_v2_` plus the md5 of the
  class name: the prefix followed by 32 hex digits. They contain only
  letters, digits, `_` and `-`, so strict backends accept them, including
  laminas-cache with its default `key_pattern`.
  `CachedMetadataFactory::keyFor($class)` returns the key for a class.
- **Versioned prefix.** The version segment (currently `v2`) changes
  whenever a release changes the shape of the cached metadata, so entries
  written by an older release are never read back.
- **Invalid keys propagate.** If the backend rejects a key with a PSR-16
  `InvalidArgumentException`, on read or write, it is rethrown rather than
  treated as a miss. An unusable key is a configuration error, and
  swallowing it would silently disable caching.
- **Best-effort.** A cache read that throws any other PSR-16
  `CacheException` counts as a miss. A failed write is ignored, and the
  freshly built metadata is still returned. An entry that isn't
  `EntityMetadata` for the requested class is also a miss, and gets
  rebuilt and overwritten.
- **Mapping errors are not cached.** If the inner factory throws
  `MappingException`, nothing is written, and the exception propagates.

## Invalidation

Entries are **not** invalidated automatically when an entity class changes.
Either:

- clear the cache, or the `contenir_db-model_metadata_` keys, as part of
  each deployment; or
- leave `CachedMetadataFactory` out in development and use
  `AttributeMetadataFactory` directly, so mapping changes are picked up
  straight away.

## Custom factories

`CachedMetadataFactory` decorates any `MetadataFactoryInterface`, so it can
cache a custom metadata source as well as the attribute reader.
