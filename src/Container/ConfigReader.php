<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Container;

use Contenir\Db\Model\Exception\ConfigurationException;

use function is_array;
use function is_int;
use function is_string;

/**
 * Reads typed values out of the untyped "contenir_db_model" array.
 *
 * @internal
 */
final readonly class ConfigReader
{
    /**
     * @param array<array-key, mixed> $config
     */
    public function __construct(
        private array $config,
    ) {}

    /**
     * @throws ConfigurationException
     *
     * @mago-expect analysis:mixed-assignment Configuration is untyped input; it is validated here.
     */
    public function int(string $key): ?int
    {
        $value = $this->config[$key] ?? null;
        if (null !== $value && ! is_int($value)) {
            throw ConfigurationException::invalidValue($key, 'an integer number of seconds', $value);
        }

        return $value;
    }

    /**
     * @throws ConfigurationException
     *
     * @mago-expect analysis:mixed-assignment Configuration is untyped input; it is validated here.
     */
    public function string(string $key): ?string
    {
        $value = $this->config[$key] ?? null;
        if (null !== $value && ! is_string($value)) {
            throw ConfigurationException::invalidValue($key, 'a service name string', $value);
        }

        return $value;
    }

    /**
     * @return array<string, string>
     *
     * @throws ConfigurationException
     *
     * @mago-expect analysis:mixed-assignment Configuration is untyped input; it is validated here.
     */
    public function stringMap(string $key): array
    {
        $value = $this->config[$key] ?? [];
        if (! is_array($value)) {
            throw ConfigurationException::invalidValue($key, 'an array of name => service', $value);
        }

        $map = [];
        foreach ($value as $name => $service) {
            if (! is_string($name) || ! is_string($service)) {
                throw ConfigurationException::invalidValue($key, 'an array of name => service', $value);
            }

            $map[$name] = $service;
        }

        return $map;
    }
}
