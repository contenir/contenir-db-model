<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use function json_encode;
use function sprintf;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PRESERVE_ZERO_FRACTION;

/**
 * Thrown when an optimistically-locked update or delete matches no row,
 * meaning the entity's version no longer matches storage because another
 * writer updated or deleted the row first.
 *
 * @api
 */
final class StaleEntityException extends RuntimeException
{
    /**
     * @param array<string, int|float|string|bool|null> $identifier
     */
    public static function forWrite(string $operation, string $className, array $identifier, mixed $version): self
    {
        return new self(sprintf(
            'Optimistic-lock failure on %s of "%s" %s: no row with version %s remains; reload and retry',
            $operation,
            $className,
            (string) json_encode($identifier, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION),
            (string) json_encode($version),
        ));
    }
}
