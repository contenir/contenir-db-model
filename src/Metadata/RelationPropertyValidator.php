<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Exception\MappingException;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Checks that a relation property is declared so the library can fill it:
 * collections typed as {@see Collection}, single relations typed as the
 * target class, and neither with a default value (an unloaded relation
 * must stay uninitialised rather than read as empty).
 *
 * @internal
 */
final readonly class RelationPropertyValidator
{
    /**
     * @param class-string $className
     *
     * @throws MappingException
     */
    public static function assertValid(
        string $className,
        ReflectionProperty $property,
        RelationMetadata $relation,
    ): void {
        $name     = $property->getName();
        $expected = $relation->isCollection() ? Collection::class : $relation->targetClass;
        $type     = $property->getType();
        if (! $type instanceof ReflectionNamedType || $type->getName() !== $expected) {
            throw MappingException::invalidRelationType($className, $name, $expected);
        }

        if ($relation->isCollection() && $type->allowsNull()) {
            throw MappingException::invalidRelationType($className, $name, 'non-nullable ' . Collection::class);
        }

        if ($property->hasDefaultValue()) {
            throw MappingException::relationWithDefault($className, $name);
        }
    }
}
