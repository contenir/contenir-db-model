<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Hydrator;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Type\TypeRegistry;

use function array_key_exists;

/**
 * Moves column values between database rows and entity properties,
 * converting through the {@see TypeRegistry}. Relation properties are not
 * touched.
 *
 * @internal
 */
final readonly class EntityHydrator
{
    public function __construct(
        private TypeRegistry $types,
        private PropertyAccessor $accessor = new PropertyAccessor(),
    ) {}

    /**
     * Assign a single PHP value to a mapped property, e.g. a generated
     * identifier or bumped version after a write.
     *
     * @template T of object
     *
     * @param T $entity
     *
     * @throws HydrationException
     */
    public function assign(object $entity, string $property, mixed $value): void
    {
        $this->accessor->set($entity, $property, $value);
    }

    /**
     * Read the entity's initialised mapped properties as database values,
     * keyed by column. Uninitialised properties are omitted.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @return array<string, int|float|string|bool|null>
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function extract(EntityMetadata $metadata, object $entity): array
    {
        $values = [];
        foreach ($metadata->fields as $field) {
            if (! $this->accessor->isInitialized($entity, $field->propertyName)) {
                continue;
            }

            $values[$field->columnName] = $this->types->toDatabase(
                $field,
                $this->accessor->get($entity, $field->propertyName),
            );
        }

        return $values;
    }

    /**
     * Build a new entity from a row without calling its constructor.
     * Mapped columns absent from the row leave their property at its
     * declared default (or uninitialised); unmapped row columns are
     * ignored.
     *
     * @template T of object
     *
     * @param EntityMetadata<T>    $metadata
     * @param array<string, mixed> $row
     *
     * @return T
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function hydrate(EntityMetadata $metadata, array $row): object
    {
        $entity = $this->accessor->instantiate($metadata->className);
        foreach ($metadata->fields as $field) {
            if (! array_key_exists($field->columnName, $row)) {
                continue;
            }

            $this->accessor->set($entity, $field->propertyName, $this->types->toPhp($field, $row[$field->columnName]));
        }

        return $entity;
    }

    /**
     * Overwrite an existing entity's mapped properties from a row. An
     * initialised readonly property is left alone when the row holds the
     * same value, and rejected when it differs.
     *
     * @template T of object
     *
     * @param EntityMetadata<T>    $metadata
     * @param T                    $entity
     * @param array<string, mixed> $row
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function refresh(EntityMetadata $metadata, object $entity, array $row): void
    {
        foreach ($metadata->fields as $field) {
            if (! array_key_exists($field->columnName, $row)) {
                continue;
            }

            $this->refreshField($metadata->className, $entity, $field, $row[$field->columnName]);
        }
    }

    /**
     * @throws HydrationException
     * @throws TypeConversionException
     */
    private function refreshField(string $className, object $entity, FieldMetadata $field, mixed $raw): void
    {
        $name = $field->propertyName;
        if (! $field->readonly || ! $this->accessor->isInitialized($entity, $name)) {
            $this->accessor->set($entity, $name, $this->types->toPhp($field, $raw));

            return;
        }

        $stored = $this->types->toDatabase($field, $this->types->toPhp($field, $raw));
        if ($this->types->toDatabase($field, $this->accessor->get($entity, $name)) !== $stored) {
            throw HydrationException::readonlyChanged($className, $name);
        }
    }
}
