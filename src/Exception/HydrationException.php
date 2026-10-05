<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use Throwable;

use function sprintf;

/**
 * Raised when an entity cannot be instantiated, or a property cannot be
 * read or written, while moving data between rows and entities.
 *
 * @api
 */
final class HydrationException extends RuntimeException
{
    public static function cannotInstantiate(string $className, Throwable $previous): self
    {
        return new self(
            sprintf('Cannot instantiate entity "%s": %s', $className, $previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function inaccessibleProperty(string $className, string $property, Throwable $previous): self
    {
        return new self(
            sprintf('Cannot access property %s::$%s: %s', $className, $property, $previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function readonlyChanged(string $className, string $property): self
    {
        return new self(sprintf(
            'Cannot refresh readonly property %s::$%s: the stored value differs from the loaded one',
            $className,
            $property,
        ));
    }
}
