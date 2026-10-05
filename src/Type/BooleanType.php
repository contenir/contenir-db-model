<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Override;

use function is_bool;

/**
 * Reads the boolean spellings drivers commonly return (1/0 integers or
 * strings, and PostgreSQL's t/f). Writes PHP booleans, which phpdb binds
 * as PDO::PARAM_BOOL.
 *
 * @api
 */
final readonly class BooleanType implements TypeConverterInterface
{
    #[Override]
    public function toDatabase(mixed $value, FieldMetadata $field): bool
    {
        return is_bool($value) ? $value : throw TypeConversionException::invalidValue($field, $value, 'bool');
    }

    #[Override]
    public function toPhp(mixed $value, FieldMetadata $field): bool
    {
        return match ($value) {
            true, 1, '1', 't', 'true'   => true,
            false, 0, '0', 'f', 'false' => false,
            default                     => throw TypeConversionException::invalidValue($field, $value, 'bool'),
        };
    }
}
