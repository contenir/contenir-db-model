<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use Contenir\Db\Model\Metadata\EntityMetadata;

use function count;
use function implode;
use function sprintf;

/**
 * Raised when finder arguments do not match the entity's mapping.
 *
 * @api
 */
final class QueryException extends InvalidArgumentException
{
    public static function invalidDirection(string $className, string $property, string $direction): self
    {
        return new self(sprintf(
            'Cannot order %s by "%s" %s: direction must be ASC or DESC',
            $className,
            $property,
            $direction,
        ));
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     */
    public static function invalidIdentifier(EntityMetadata $metadata): self
    {
        $properties = [];
        foreach ($metadata->identifier as $field) {
            $properties[] = $field->propertyName;
        }

        return new self(sprintf(
            'find() on %s needs %s',
            $metadata->className,
            1 === count($properties)
                ? sprintf('a scalar value for $%s', $properties[0])
                : sprintf('an array with keys [%s]', implode(', ', $properties)),
        ));
    }

    public static function unknownProperty(string $className, string $property): self
    {
        return new self(sprintf(
            '"%s" is not a mapped column property of %s; criteria and ordering use property names',
            $property,
            $className,
        ));
    }
}
