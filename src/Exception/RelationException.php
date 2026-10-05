<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use function sprintf;

/**
 * Raised when a relation cannot be loaded or assigned.
 *
 * @api
 */
final class RelationException extends RuntimeException
{
    public static function missingRelated(string $className, string $property): self
    {
        return new self(sprintf(
            'Relation %s::$%s found no related row, but the property is not nullable; check the foreign key '
                . 'or declare the property nullable',
            $className,
            $property,
        ));
    }

    public static function notLoaded(string $className, string $property): self
    {
        return new self(sprintf(
            'Relation %s::$%s is not loaded: the entity is not managed, so preload() it or load the entity '
                . 'through a repository',
            $className,
            $property,
        ));
    }

    public static function undefinedProperty(string $className, string $property): self
    {
        return new self(sprintf('Undefined property %s::$%s', $className, $property));
    }

    public static function unexpectedEntity(string $expected, string $actual): self
    {
        return new self(sprintf('preload() expected entities of %s, got %s', $expected, $actual));
    }
}
