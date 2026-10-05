# Container integration

The package ships PSR-11 factories, a `ConfigProvider` for Mezzio-style
config aggregation, and a `Module` class for laminas-mvc. With
`laminas/laminas-component-installer`, the right one is registered
automatically on install.

Manual registration:

```php
// Mezzio: config/config.php
$aggregator = new ConfigAggregator([
    \PhpDb\ConfigProvider::class,
    \Contenir\Db\Model\ConfigProvider::class,
    // ...
]);

// laminas-mvc: config/modules.config.php
return [
    // ...
    'Contenir\\Db\\Model',
];
```

The `Module` exposes the same services under laminas-mvc's
`service_manager` key. Neither entry point requires laminas-mvc or
laminas-servicemanager as a dependency.

## Services

| Service | Factory | Builds |
| --- | --- | --- |
| `Contenir\Db\Model\EntityManager` | `Container\EntityManagerFactory` | The entity manager, from the configured adapter, metadata factory and type registry |
| `Contenir\Db\Model\Metadata\MetadataFactoryInterface` | `Container\MetadataFactoryFactory` | `AttributeMetadataFactory`, wrapped in `CachedMetadataFactory` when a cache is configured |
| `Contenir\Db\Model\Type\TypeRegistry` | `Container\TypeRegistryFactory` | `TypeRegistry::withDefaults()` plus configured converters |

Override any of these in your own `dependencies` to customise them.

## Configuration

```php
// config/autoload/db-model.global.php
return [
    'contenir_db_model' => [
        // phpdb adapter service name
        'adapter'            => \PhpDb\Adapter\AdapterInterface::class,

        // PSR-16 cache service for mapping metadata; null disables caching
        'metadata_cache'     => 'cache.metadata',
        // seconds, or null for the cache's default
        'metadata_cache_ttl' => null,

        // extra type converters: converter name or class => converter service name
        'types' => [
            'money'            => App\Db\MoneyType::class,
            App\Money::class   => App\Db\MoneyType::class,
        ],
    ],
];
```

- **`adapter`:** any service that returns `PhpDb\Adapter\AdapterInterface`.
  Use a named adapter service to point the model at a non-default database.
- **`metadata_cache`:** recommended in production. See
  [metadata caching](metadata-caching.md). Clear it on deploy.
- **`types`:** each converter must be **registered as a container service**
  (for example as an invokable). Keys follow the
  [type registry](types.md#custom-converters) rules: a name is used with
  `#[Column(type: '…')]`, and a class name applies to every property of
  that type.

Wrong types (for example a non-string service name, or a service that
isn't a PSR-16 cache) fail at construction with a
`ConfigurationException` that names the key or service.

## Custom repositories

Register custom repositories whose constructor takes only the
`EntityManager` with `RepositoryFactory`:

```php
use Contenir\Db\Model\Container\RepositoryFactory;

return [
    'dependencies' => [
        'factories' => [
            App\Repository\UserRepository::class => RepositoryFactory::class,
        ],
    ],
];
```

Repositories with additional dependencies need their own factory. Fetch
`EntityManager::class` from the container and pass it through.

## Lifetime of the entity manager

The container shares one `EntityManager` per container. That's right for
PHP-FPM, where the container lives for a single request.

In **long-running servers** (Swoole, RoadRunner, FrankenPHP worker mode,
ReactPHP) and **queue workers**, the container outlives requests, so the
shared manager's identity map would carry entities from one request into
the next. Either:

- call `$em->clear()` at the end of every request or job (for example in
  middleware or a worker loop), or
- configure the container to build a fresh `EntityManager` per request.

See [identity map: memory and long-running processes](identity-map.md#memory-and-long-running-processes).
