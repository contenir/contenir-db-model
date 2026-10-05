<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use BackedEnum;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Override;

use function is_a;
use function is_int;
use function is_string;

/**
 * Converts backing values to and from the backed enum the property is
 * declared as. Backing values are compared as strings because drivers
 * often return integer columns as numeric strings.
 *
 * @api
 */
final readonly class BackedEnumType implements TypeConverterInterface
{
    /**
     * @param class-string<BackedEnum> $enum
     *
     * @return list<BackedEnum>
     *
     * @mago-expect analysis:possibly-static-access-on-interface
     */
    private static function cases(string $enum): array
    {
        return $enum::cases();
    }

    /**
     * @return class-string<BackedEnum>
     *
     * @throws TypeConversionException
     */
    private static function enumClass(FieldMetadata $field): string
    {
        $class = $field->type->phpType;
        if (null === $class || ! is_a($class, BackedEnum::class, allow_string: true)) {
            throw TypeConversionException::unresolvableType($field);
        }

        return $class;
    }

    #[Override]
    public function toDatabase(mixed $value, FieldMetadata $field): int|string
    {
        $enum = self::enumClass($field);

        return $value instanceof $enum
            ? $value->value
            : throw TypeConversionException::invalidValue($field, $value, $enum);
    }

    #[Override]
    public function toPhp(mixed $value, FieldMetadata $field): BackedEnum
    {
        $enum = self::enumClass($field);
        if ($value instanceof $enum) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            foreach (self::cases($enum) as $case) {
                if ((string) $case->value === (string) $value) {
                    return $case;
                }
            }
        }

        throw TypeConversionException::invalidValue($field, $value, $enum);
    }
}
