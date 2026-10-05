<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Mapping\Table;
use ReflectionClass;
use ReflectionException;

use function array_key_exists;

/**
 * Reads #[Table], #[Column], #[Id] and #[Version] from an entity class and
 * validates the resulting key layout. Memoised per class.
 *
 * @internal
 */
final class ColumnMappingReader
{
    /**
     * @var array<class-string, ColumnMapping>
     */
    private array $mappings = [];

    /**
     * @param class-string $className
     *
     * @return ReflectionClass<object>
     *
     * @throws MappingException
     */
    public static function reflect(string $className): ReflectionClass
    {
        try {
            return new ReflectionClass($className);
        } catch (ReflectionException) {
            throw MappingException::unknownClass($className);
        }
    }

    /**
     * @param class-string $className
     *
     * @throws MappingException
     */
    public function read(string $className): ColumnMapping
    {
        if (array_key_exists($className, $this->mappings)) {
            return $this->mappings[$className];
        }

        $class = self::reflect($className);
        $table = PropertyFieldReader::first($class->getAttributes(Table::class));
        if (null === $table) {
            throw MappingException::notAnEntity($className);
        }

        if ($class->isAbstract()) {
            throw MappingException::abstractEntity($className);
        }

        $mapping = new ColumnMapping($className, $table->name, $table->schema, $this->fieldsOf($class));
        MappingValidator::assertKeysAreValid($mapping);

        return $this->mappings[$className] = $mapping;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return array<string, FieldMetadata>
     *
     * @throws MappingException
     */
    private function fieldsOf(ReflectionClass $class): array
    {
        $fields  = [];
        $columns = [];
        foreach ($class->getProperties() as $property) {
            $field = PropertyFieldReader::read($class->getName(), $property);
            if (null === $field) {
                continue;
            }

            if (array_key_exists($field->columnName, $columns)) {
                throw MappingException::duplicateColumn($class->getName(), $field->columnName);
            }

            $columns[$field->columnName]  = true;
            $fields[$field->propertyName] = $field;
        }

        return $fields;
    }
}
