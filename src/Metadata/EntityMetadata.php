<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use PhpDb\Sql\TableIdentifier;

use function array_key_exists;
use function array_keys;
use function array_map;

/**
 * Immutable mapping of one entity class to its table, columns and
 * relations, as produced by {@see MetadataFactoryInterface}.
 *
 * @api
 *
 * @template-covariant T of object
 */
final readonly class EntityMetadata
{
    /**
     * @var array<string, FieldMetadata>
     */
    private array $fieldsByColumn;

    /**
     * Primary-key fields in declaration order.
     *
     * @var list<FieldMetadata>
     */
    public array $identifier;

    /**
     * Optimistic-locking version field, if declared.
     */
    public ?FieldMetadata $version;

    /**
     * Database-generated primary-key field, if declared.
     */
    public ?FieldMetadata $generatedIdentifier;

    /**
     * @param class-string<T>                 $className
     * @param array<string, FieldMetadata>    $fields    keyed by property name
     * @param array<string, RelationMetadata> $relations keyed by property name
     */
    public function __construct(
        public string $className,
        public string $table,
        public ?string $schema,
        public array $fields,
        public array $relations = [],
    ) {
        $byColumn   = [];
        $identifier = [];
        $roles      = [];

        foreach ($fields as $field) {
            $byColumn[$field->columnName] = $field;
            $roles[$field->role->name]    = $field;
            if ($field->isIdentifier()) {
                $identifier[] = $field;
            }
        }

        $this->fieldsByColumn      = $byColumn;
        $this->identifier          = $identifier;
        $this->version             = $roles[FieldRole::Version->name] ?? null;
        $this->generatedIdentifier = $roles[FieldRole::GeneratedIdentifier->name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getColumnNames(): array
    {
        return array_keys($this->fieldsByColumn);
    }

    /**
     * @throws MappingException When the property is not column-mapped.
     */
    public function getField(string $propertyName): FieldMetadata
    {
        return $this->fields[$propertyName] ?? throw MappingException::unknownField($this->className, $propertyName);
    }

    /**
     * @throws MappingException When no property maps to the column.
     */
    public function getFieldForColumn(string $columnName): FieldMetadata
    {
        return (
            $this->fieldsByColumn[$columnName] ?? throw MappingException::unknownColumn($this->className, $columnName)
        );
    }

    /**
     * @return list<string>
     */
    public function getIdentifierColumns(): array
    {
        return array_map(static fn(FieldMetadata $field): string => $field->columnName, $this->identifier);
    }

    /**
     * @throws MappingException When the property is not a declared relation.
     */
    public function getRelation(string $propertyName): RelationMetadata
    {
        return (
            $this->relations[$propertyName] ?? throw MappingException::unknownRelation($this->className, $propertyName)
        );
    }

    public function getTableIdentifier(): TableIdentifier
    {
        return new TableIdentifier($this->table, $this->schema);
    }

    public function hasColumn(string $columnName): bool
    {
        return array_key_exists($columnName, $this->fieldsByColumn);
    }

    public function hasField(string $propertyName): bool
    {
        return array_key_exists($propertyName, $this->fields);
    }

    public function hasRelation(string $propertyName): bool
    {
        return array_key_exists($propertyName, $this->relations);
    }
}
