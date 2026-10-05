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
- **Keys.** Keys are `contenir.db-model.metadata.v1.` plus the class name,
  with `\` replaced by `.`. For example,
  `contenir.db-model.metadata.v1.App.Entity.User`.
  `CachedMetadataFactory::keyFor($class)` returns the key for a class.
- **Versioned prefix.** The `v1` segment changes whenever a release changes
  the shape of the cached metadata, so entries written by an older release
  are never read back.
- **Best-effort.** A cache read that throws a PSR-16 `CacheException` counts
  as a miss. A failed write is ignored, and the freshly built metadata is
  still returned. An entry that isn't `EntityMetadata` for the requested
  class is also a miss, and gets rebuilt and overwritten.
- **Mapping errors are not cached.** If the inner factory throws
  `MappingException`, nothing is written, and the exception propagates.

## Invalidation

Entries are **not** invalidated automatically when an entity class changes.
Either:

- clear the cache, or the `contenir.db-model.metadata.` keys, as part of
  each deployment; or
- leave `CachedMetadataFactory` out in development and use
  `AttributeMetadataFactory` directly, so mapping changes are picked up
  straight away.

## Custom factories

`CachedMetadataFactory` decorates any `MetadataFactoryInterface`, so it can
cache a custom metadata source as well as the attribute reader.
