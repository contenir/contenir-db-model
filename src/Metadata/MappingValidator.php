<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Mapping\RelationInterface;
use ReflectionAttribute;
use ReflectionProperty;

use function array_keys;
use function count;
use function in_array;

/**
 * Consistency rules for column-mapped fields and the key layout of an entity.
 *
 * @internal
 */
final readonly class MappingValidator
{
    /**
     * @param class-string $className
     *
     * @throws MappingException
     */
    public static function assertFieldIsValid(string $className, FieldMetadata $field): void
    {
        if ($field->readonly && $field->role->isWrittenBack()) {
            throw MappingException::readonlyWrittenProperty($className, $field->propertyName);
        }

        $isIntVersion = 'int' === $field->type->phpType && ! $field->type->nullable;
        if (FieldRole::Version === $field->role && ! $isIntVersion) {
            throw MappingException::invalidVersionType($className, $field->propertyName);
        }
    }

    /**
     * @throws MappingException
     */
    public static function assertKeysAreValid(ColumnMapping $mapping): void
    {
        $roles = [];
        foreach ($mapping->fields as $field) {
            $roles[] = $field->role;
        }

        $identifiers = count($mapping->identifierColumns());
        if (0 === $identifiers) {
            throw MappingException::missingIdentifier($mapping->className);
        }

        if ($identifiers > 1 && in_array(FieldRole::GeneratedIdentifier, $roles, strict: true)) {
            throw MappingException::compositeGeneratedIdentifier($mapping->className);
        }

        if (count(array_keys($roles, FieldRole::Version, strict: true)) > 1) {
            throw MappingException::multipleVersions($mapping->className);
        }
    }

    /**
     * @param class-string $className
     *
     * @throws MappingException
     */
    public static function assertPropertyIsMappable(string $className, ReflectionProperty $property): void
    {
        if ([] !== $property->getAttributes(RelationInterface::class, ReflectionAttribute::IS_INSTANCEOF)) {
            throw MappingException::relationWithColumn($className, $property->getName());
        }

        if ($property->isStatic()) {
            throw MappingException::staticProperty($className, $property->getName());
        }
    }
}
