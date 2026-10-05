<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Value\SensitiveString;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Derives a {@see FieldType} from a property's declared type and its
 * optional #[Column] settings.
 *
 * @internal
 */
final readonly class FieldTypeResolver
{
    /**
     * @param class-string $className
     *
     * @throws MappingException
     */
    public static function resolve(string $className, ReflectionProperty $property, ?Column $column): FieldType
    {
        $type = $property->getType();
        if (null === $type) {
            throw MappingException::untypedProperty($className, $property->getName());
        }

        $phpType  = $type instanceof ReflectionNamedType ? $type->getName() : null;
        $typeName = $column?->type;
        if (null === $phpType && null === $typeName) {
            throw MappingException::ambiguousType($className, $property->getName());
        }

        $sensitive = ($column->sensitive ?? false) || SensitiveString::class === $phpType;

        return new FieldType($phpType, $type->allowsNull(), $typeName, $sensitive);
    }
}
