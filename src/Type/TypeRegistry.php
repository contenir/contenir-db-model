<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use BackedEnum;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Value\SensitiveString;
use DateTimeInterface;

use function array_key_exists;
use function is_a;

/**
 * Immutable lookup from field to {@see TypeConverterInterface}, plus the
 * null handling shared by every converter.
 *
 * Resolution order for a field:
 *  1. the explicit #[Column(type: ...)] name;
 *  2. a converter registered under the declared property type (builtin
 *     name or class name);
 *  3. the "enum" converter for any BackedEnum;
 *  4. the "datetime" converter for any DateTimeInterface.
 *
 * @api
 */
final readonly class TypeRegistry
{
    /**
     * @param array<string, TypeConverterInterface> $converters keyed by type name or class name
     */
    public function __construct(
        private array $converters = [],
    ) {}

    public static function withDefaults(): self
    {
        return new self([
            'int'                  => new IntegerType(),
            'float'                => new FloatType(),
            'string'               => new StringType(),
            'bool'                 => new BooleanType(),
            'datetime'             => new DateTimeType(),
            'date'                 => new DateTimeType('Y-m-d'),
            'json'                 => new JsonType(),
            'enum'                 => new BackedEnumType(),
            SensitiveString::class => new SensitiveStringType(),
        ]);
    }

    /**
     * @throws TypeConversionException When no converter applies to the field.
     */
    public function converterFor(FieldMetadata $field): TypeConverterInterface
    {
        $name = $field->type->typeName ?? $this->implicitName($field);

        return $this->converters[$name] ?? throw TypeConversionException::noConverter($field, $name);
    }

    /**
     * Convert a property value for binding as a statement parameter.
     *
     * @throws TypeConversionException
     */
    public function toDatabase(FieldMetadata $field, mixed $value): int|float|string|bool|null
    {
        return null === $value ? null : $this->converterFor($field)->toDatabase($value, $field);
    }

    /**
     * Convert a database value for hydration onto the field's property.
     *
     * @throws TypeConversionException
     */
    public function toPhp(FieldMetadata $field, mixed $value): mixed
    {
        if (null === $value) {
            return $field->type->nullable ? null : throw TypeConversionException::unexpectedNull($field);
        }

        return $this->converterFor($field)->toPhp($value, $field);
    }

    /**
     * Return a registry with $converter registered under $name, replacing
     * any existing converter of that name.
     */
    public function withConverter(string $name, TypeConverterInterface $converter): self
    {
        return new self([$name => $converter] + $this->converters);
    }

    /**
     * @throws TypeConversionException
     */
    private function implicitName(FieldMetadata $field): string
    {
        $type = $field->type->phpType ?? throw TypeConversionException::unresolvableType($field);

        return match (true) {
            array_key_exists($type, $this->converters) => $type,
            is_a($type, BackedEnum::class, allow_string: true)        => 'enum',
            is_a($type, DateTimeInterface::class, allow_string: true) => 'datetime',
            default                                                   => $type,
        };
    }
}
