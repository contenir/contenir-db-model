<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Container;

use Contenir\Db\Model\Exception\ConfigurationException;
use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_array;

/**
 * Typed view of the "contenir_db_model" configuration key.
 *
 * @internal
 */
final readonly class ModuleConfig
{
    public const string KEY = 'contenir_db_model';

    /**
     * @param array<string, string> $types converter name or class => converter service name
     */
    private function __construct(
        public string $adapter,
        public ?string $metadataCache,
        public ?int $metadataCacheTtl,
        public array $types,
    ) {}

    /**
     * @throws ConfigurationException
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment Configuration is untyped input; ConfigReader validates it.
     */
    public static function from(ContainerInterface $container): self
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $module = is_array($config) ? $config[self::KEY] ?? [] : [];
        if (! is_array($module)) {
            throw ConfigurationException::invalidValue('', 'an array', $module);
        }

        $reader = new ConfigReader($module);

        return new self(
            $reader->string('adapter') ?? AdapterInterface::class,
            $reader->string('metadata_cache'),
            $reader->int('metadata_cache_ttl'),
            $reader->stringMap('types'),
        );
    }
}
