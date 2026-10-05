<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Value;

use SensitiveParameter;

use function hash_equals;

/**
 * String whose contents stay out of debug output. var_dump() and print_r()
 * show "[redacted]", the constructor argument is hidden from stack traces,
 * and there is deliberately no __toString(): the value is only reachable
 * through {@see self::reveal()}.
 *
 * Intended for password hashes, API tokens and similar column values. It
 * does not encrypt anything, and serialize()/var_export() still include
 * the raw value.
 *
 * @api
 */
final readonly class SensitiveString
{
    public const string REDACTED = '[redacted]';

    public function __construct(
        #[SensitiveParameter]
        private string $value,
    ) {}

    /**
     * Constant-time comparison, safe for comparing secrets.
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function reveal(): string
    {
        return $this->value;
    }

    /**
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => self::REDACTED];
    }
}
