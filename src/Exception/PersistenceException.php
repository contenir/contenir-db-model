<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use function json_encode;
use function sprintf;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PRESERVE_ZERO_FRACTION;

/**
 * Raised when an entity cannot be written, removed or reloaded.
 *
 * @api
 */
final class PersistenceException extends RuntimeException
{
    public static function missingGeneratedValue(string $className, string $property): self
    {
        return new self(sprintf(
            'The database returned no generated value for %s::$%s after insert',
            $className,
            $property,
        ));
    }

    public static function missingIdentifier(string $operation, string $className): self
    {
        return new self(sprintf(
            'Cannot %s "%s": the entity has no complete primary key; it has not been saved',
            $operation,
            $className,
        ));
    }

    public static function noResult(): self
    {
        return new self('The database driver returned no result for a write statement');
    }

    /**
     * @param array<string, int|float|string|bool|null> $identifier
     */
    public static function rowNotFound(string $className, array $identifier): self
    {
        return new self(sprintf(
            'No row for "%s" %s exists; it may have been deleted',
            $className,
            (string) json_encode($identifier, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION),
        ));
    }
}
