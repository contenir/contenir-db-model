<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use function count;
use function implode;
use function sprintf;

/**
 * Raised when an entity's mapping attributes are missing, contradictory or
 * reference columns that do not exist.
 *
 * @api
 */
final class MappingException extends InvalidArgumentException
{
    public static function abstractEntity(string $className): self
    {
        return new self(sprintf('Entity class "%s" must be instantiable, but it is abstract', $className));
    }

    public static function ambiguousType(string $className, string $property): self
    {
        return new self(sprintf(
            'Mapped property %s::$%s has a union or intersection type; name a converter with #[Column(type: ...)]',
            $className,
            $property,
        ));
    }

    public static function compositeGeneratedIdentifier(string $className): self
    {
        return new self(sprintf(
            'Entity "%s" has a generated #[Id] within a composite key; generated keys must be the only #[Id]',
            $className,
        ));
    }

    public static function conflictingRoles(string $className, string $property): self
    {
        return new self(sprintf('Property %s::$%s cannot be both #[Id] and #[Version]', $className, $property));
    }

    public static function duplicateColumn(string $className, string $column): self
    {
        return new self(sprintf('Entity "%s" maps column "%s" more than once', $className, $column));
    }

    public static function invalidOrderDirection(string $className, string $relation, string $direction): self
    {
        return new self(sprintf(
            'Relation %s::$%s orders by "%s"; direction must be ASC or DESC',
            $className,
            $relation,
            $direction,
        ));
    }

    public static function invalidRelationType(string $className, string $property, string $expected): self
    {
        return new self(sprintf('Relation property %s::$%s must be typed as %s', $className, $property, $expected));
    }

    public static function invalidVersionType(string $className, string $property): self
    {
        return new self(sprintf(
            '#[Version] property %s::$%s must be typed as non-nullable int',
            $className,
            $property,
        ));
    }

    /**
     * @param list<string> $local
     * @param list<string> $target
     */
    public static function keyCountMismatch(string $className, string $relation, array $local, array $target): self
    {
        return new self(sprintf(
            'Relation %s::$%s pairs %d local column(s) [%s] with %d target column(s) [%s]',
            $className,
            $relation,
            count($local),
            implode(', ', $local),
            count($target),
            implode(', ', $target),
        ));
    }

    public static function missingIdentifier(string $className): self
    {
        return new self(sprintf('Entity "%s" declares no #[Id] property', $className));
    }

    public static function multipleRelations(string $className, string $property): self
    {
        return new self(sprintf('Property %s::$%s declares more than one relation attribute', $className, $property));
    }

    public static function multipleVersions(string $className): self
    {
        return new self(sprintf('Entity "%s" declares more than one #[Version] property', $className));
    }

    public static function notAnEntity(string $className): self
    {
        return new self(sprintf('Class "%s" is not an entity: it has no #[Table] attribute', $className));
    }

    public static function readonlyWrittenProperty(string $className, string $property): self
    {
        return new self(sprintf(
            'Property %s::$%s is written back after persisting (generated #[Id] or #[Version]) and must not be readonly',
            $className,
            $property,
        ));
    }

    public static function relationWithColumn(string $className, string $property): self
    {
        return new self(sprintf('Property %s::$%s cannot be both a relation and a column', $className, $property));
    }

    public static function relationWithDefault(string $className, string $property): self
    {
        return new self(sprintf(
            'Relation property %s::$%s must not declare a default value; unloaded relations stay uninitialised',
            $className,
            $property,
        ));
    }

    public static function staticProperty(string $className, string $property): self
    {
        return new self(sprintf('Mapped property %s::$%s must not be static', $className, $property));
    }

    public static function unknownClass(string $className): self
    {
        return new self(sprintf('Entity class "%s" does not exist', $className));
    }

    public static function unknownColumn(string $className, string $column): self
    {
        return new self(sprintf('Column "%s" is not mapped on "%s"', $column, $className));
    }

    public static function unknownField(string $className, string $property): self
    {
        return new self(sprintf('Property %s::$%s is not a mapped column', $className, $property));
    }

    public static function unknownRelation(string $className, string $property): self
    {
        return new self(sprintf('Property %s::$%s is not a declared relation', $className, $property));
    }

    public static function unknownRelationColumn(
        string $className,
        string $relation,
        string $entity,
        string $column,
    ): self {
        return new self(sprintf(
            'Relation %s::$%s references column "%s", which is not mapped on "%s"',
            $className,
            $relation,
            $column,
            $entity,
        ));
    }

    public static function unsupportedRelation(string $className, string $property, string $attribute): self
    {
        return new self(sprintf('Relation %s::$%s uses unsupported attribute "%s"', $className, $property, $attribute));
    }

    public static function untypedProperty(string $className, string $property): self
    {
        return new self(sprintf('Mapped property %s::$%s must declare a type', $className, $property));
    }
}
