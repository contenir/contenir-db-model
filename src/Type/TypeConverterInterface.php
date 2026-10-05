<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;

/**
 * Converts one kind of value between its database and PHP representations.
 * Converters never receive null: {@see TypeRegistry} handles nulls before
 * delegating.
 *
 * @api
 */
interface TypeConverterInterface
{
    /**
     * @return scalar
     *
     * @throws TypeConversionException When $value is not of the field's type.
     */
    public function toDatabase(mixed $value, FieldMetadata $field): int|float|string|bool;

    /**
     * @throws TypeConversionException When $value cannot represent the field's type.
     */
    public function toPhp(mixed $value, FieldMetadata $field): mixed;
}
