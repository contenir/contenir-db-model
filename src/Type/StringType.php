<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Override;

use function is_float;
use function is_int;
use function is_string;

/**
 * Accepts numeric database values for string properties, since some
 * drivers return numeric-looking columns as int or float.
 *
 * @api
 */
final readonly class StringType implements TypeConverterInterface
{
    #[Override]
    public function toDatabase(mixed $value, FieldMetadata $field): string
    {
        return is_string($value) ? $value : throw TypeConversionException::invalidValue($field, $value, 'string');
    }

    #[Override]
    public function toPhp(mixed $value, FieldMetadata $field): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw TypeConversionException::invalidValue($field, $value, 'string');
    }
}
