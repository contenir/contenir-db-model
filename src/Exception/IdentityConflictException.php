<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use function json_encode;
use function sprintf;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PRESERVE_ZERO_FRACTION;

/**
 * Raised when a second, different object is registered under an identity
 * that is already held, i.e. two in-memory entities claim the same
 * primary key.
 *
 * @api
 */
final class IdentityConflictException extends RuntimeException
{
    /**
     * @param array<string, int|float|string|bool> $identifier
     */
    public static function alreadyManaged(string $className, array $identifier): self
    {
        return new self(sprintf(
            'Another instance of "%s" with identifier %s is already managed; load it instead of creating a new one',
            $className,
            (string) json_encode($identifier, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION),
        ));
    }
}
