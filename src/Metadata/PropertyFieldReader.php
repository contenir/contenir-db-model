<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Version;
use ReflectionAttribute;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Turns one reflected property and its #[Column], #[Id] and #[Version]
 * attributes into {@see FieldMetadata}.
 *
 * @internal
 */
final readonly class PropertyFieldReader
{
    /**
     * @template A of object
     *
     * @param list<ReflectionAttribute<A>> $attributes
     *
     * @return A|null
     */
    public static function first(array $attributes): ?object
    {
        return [] === $attributes ? null : $attributes[0]->newInstance();
    }

    /**
     * @param class-string $className
     *
     * @throws MappingException
     */
    public static function read(string $className, ReflectionProperty $property): ?FieldMetadata
    {
        $column = self::first($property->getAttributes(Column::class));
        $role   = self::roleOf($className, $property);
        if (null === $column && null === $role) {
            return null;
        }

        MappingValidator::assertPropertyIsMappable($className, $property);
        $name = $property->getName();

        $field = new FieldMetadata(
            $name,
            $column->name ?? $name,
            self::typeOf($className, $property, $column?->type),
            $role ?? FieldRole::Column,
            $property->isReadOnly(),
        );

        MappingValidator::assertFieldIsValid($className, $field);

        return $field;
    }

    /**
     * @param class-string $className
     *
     * @throws MappingException
     */
    private static function roleOf(string $className, ReflectionProperty $property): ?FieldRole
    {
        $id      = self::first($property->getAttributes(Id::class));
        $version = [] !== $property->getAttributes(Version::class);

        if (null !== $id && $version) {
            throw MappingException::conflictingRoles($className, $property->getName());
        }

        if (null !== $id) {
            return $id->generated ? FieldRole::GeneratedIdentifier : FieldRole::Identifier;
        }

        return $version ? FieldRole::Version : null;
    }

    /**
     * @param class-string $className
     *
     * @throws MappingException
     */
    private static function typeOf(string $className, ReflectionProperty $property, ?string $typeName): FieldType
    {
        $type = $property->getType();
        if (null === $type) {
            throw MappingException::untypedProperty($className, $property->getName());
        }

        $phpType = $type instanceof ReflectionNamedType ? $type->getName() : null;
        if (null === $phpType && null === $typeName) {
            throw MappingException::ambiguousType($className, $property->getName());
        }

        return new FieldType($phpType, $type->allowsNull(), $typeName);
    }
}
