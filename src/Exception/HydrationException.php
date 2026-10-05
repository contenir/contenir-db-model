<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use Throwable;

use function implode;
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

    /**
     * @param list<string> $columns
     */
    public static function missingIdentifier(string $className, array $columns): self
    {
        return new self(sprintf(
            'Cannot load entity "%s": the row lacks a non-null value for identifier column(s) [%s]; '
                . 'include the primary key in the select',
            $className,
            implode(', ', $columns),
        ));
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
