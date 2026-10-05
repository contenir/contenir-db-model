<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use Throwable;

use function get_debug_type;
use function sprintf;

/**
 * Raised when the "contenir_db_model" configuration is malformed or names
 * services of the wrong type.
 *
 * @api
 */
final class ConfigurationException extends InvalidArgumentException
{
    public static function invalidService(string $name, string $expected, mixed $service): self
    {
        return new self(sprintf(
            'Service "%s" must be an instance of %s, got %s',
            $name,
            $expected,
            get_debug_type($service),
        ));
    }

    public static function invalidValue(string $key, string $expected, mixed $value): self
    {
        return new self(sprintf(
            'Configuration "contenir_db_model.%s" must be %s, got %s',
            $key,
            $expected,
            get_debug_type($value),
        ));
    }

    public static function unbuildableRepository(string $className, Throwable $previous): self
    {
        return new self(
            sprintf(
                'Cannot build repository "%s" with the EntityManager as its only argument: %s',
                $className,
                $previous->getMessage(),
            ),
            0,
            $previous,
        );
    }
}
