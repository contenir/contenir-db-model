<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use Contenir\Db\Model\Metadata\FieldMetadata;

use function get_debug_type;
use function is_scalar;
use function sprintf;
use function var_export;

/**
 * Raised when a value cannot be converted between its database and PHP
 * representations, or no converter is registered for a field's type.
 *
 * @api
 */
final class TypeConversionException extends RuntimeException
{
    public static function invalidValue(FieldMetadata $field, mixed $value, string $expected): self
    {
        return new self(sprintf(
            'Cannot convert %s for property $%s (column "%s") to %s',
            self::describe($value),
            $field->propertyName,
            $field->columnName,
            $expected,
        ));
    }

    public static function noConverter(FieldMetadata $field, string $type): self
    {
        return new self(sprintf(
            'No type converter is registered for "%s" (property $%s, column "%s")',
            $type,
            $field->propertyName,
            $field->columnName,
        ));
    }

    public static function unexpectedNull(FieldMetadata $field): self
    {
        return new self(sprintf(
            'Column "%s" is NULL but property $%s is not nullable',
            $field->columnName,
            $field->propertyName,
        ));
    }

    public static function unresolvableType(FieldMetadata $field): self
    {
        return new self(sprintf(
            'Cannot determine a type converter for property $%s (column "%s"); name one with #[Column(type: ...)]',
            $field->propertyName,
            $field->columnName,
        ));
    }

    private static function describe(mixed $value): string
    {
        if (is_scalar($value)) {
            return get_debug_type($value) . ' ' . var_export($value, return: true);
        }

        return get_debug_type($value);
    }
}
