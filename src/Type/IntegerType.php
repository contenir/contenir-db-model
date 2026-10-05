<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Override;

use function filter_var;
use function is_int;
use function is_string;

use const FILTER_VALIDATE_INT;

/**
 * Accepts integers and integer strings from the database; anything else,
 * including floats and booleans, is rejected rather than coerced.
 *
 * @api
 */
final readonly class IntegerType implements TypeConverterInterface
{
    #[Override]
    public function toDatabase(mixed $value, FieldMetadata $field): int
    {
        return is_int($value) ? $value : throw TypeConversionException::invalidValue($field, $value, 'int');
    }

    #[Override]
    public function toPhp(mixed $value, FieldMetadata $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        $int = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        return false === $int ? throw TypeConversionException::invalidValue($field, $value, 'int') : $int;
    }
}
