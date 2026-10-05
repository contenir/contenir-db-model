<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Identity;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Type\TypeRegistry;

use function array_key_exists;

/**
 * Produces normalised identifiers (database-form primary-key values keyed
 * by column) from rows and from entities, so the same key results whether
 * a driver returned "5" or the property holds 5.
 *
 * @internal
 */
final readonly class IdentifierResolver
{
    public function __construct(
        private TypeRegistry $types,
        private PropertyAccessor $accessor = new PropertyAccessor(),
    ) {}

    /**
     * The entity's identifier, or null when any primary-key property is
     * uninitialised or null (an entity that has not been inserted yet).
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @return array<string, int|float|string|bool>|null
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function fromEntity(EntityMetadata $metadata, object $entity): ?array
    {
        $identifier = [];
        foreach ($metadata->identifier as $field) {
            if (! $this->accessor->isInitialized($entity, $field->propertyName)) {
                return null;
            }

            $value = $this->types->toDatabase($field, $this->accessor->get($entity, $field->propertyName));
            if (null === $value) {
                return null;
            }

            $identifier[$field->columnName] = $value;
        }

        return $identifier;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T>    $metadata
     * @param array<string, mixed> $row
     *
     * @return array<string, int|float|string|bool>
     *
     * @throws HydrationException      When the row lacks a primary-key value.
     * @throws TypeConversionException
     */
    public function fromRow(EntityMetadata $metadata, array $row): array
    {
        $identifier = [];
        foreach ($metadata->identifier as $field) {
            $value = array_key_exists($field->columnName, $row) && null !== $row[$field->columnName]
                ? $this->types->toDatabase($field, $this->types->toPhp($field, $row[$field->columnName]))
                : null;
            if (null === $value) {
                throw HydrationException::missingIdentifier($metadata->className, $metadata->getIdentifierColumns());
            }

            $identifier[$field->columnName] = $value;
        }

        return $identifier;
    }
}
