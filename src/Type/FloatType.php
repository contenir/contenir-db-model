<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Override;

use function is_numeric;

/**
 * @api
 */
final readonly class FloatType implements TypeConverterInterface
{
    #[Override]
    public function toDatabase(mixed $value, FieldMetadata $field): float
    {
        return $this->convert($value, $field);
    }

    #[Override]
    public function toPhp(mixed $value, FieldMetadata $field): float
    {
        return $this->convert($value, $field);
    }

    /**
     * @throws TypeConversionException
     */
    private function convert(mixed $value, FieldMetadata $field): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        throw TypeConversionException::invalidValue($field, $value, 'float');
    }
}
