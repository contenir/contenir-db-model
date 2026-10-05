<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Value\SensitiveString;
use Override;
use SensitiveParameter;

use function is_string;

/**
 * Stores a {@see SensitiveString} as an ordinary text column.
 *
 * @api
 */
final readonly class SensitiveStringType implements TypeConverterInterface
{
    #[Override]
    public function toDatabase(#[SensitiveParameter] mixed $value, FieldMetadata $field): string
    {
        return $value instanceof SensitiveString
            ? $value->reveal()
            : throw TypeConversionException::invalidValue($field, $value, SensitiveString::class);
    }

    #[Override]
    public function toPhp(#[SensitiveParameter] mixed $value, FieldMetadata $field): SensitiveString
    {
        return is_string($value)
            ? new SensitiveString($value)
            : throw TypeConversionException::invalidValue($field, $value, SensitiveString::class);
    }
}
