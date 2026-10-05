<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use function array_key_exists;

/**
 * Table and column-mapped fields of one entity class, without relations.
 *
 * @internal
 */
final readonly class ColumnMapping
{
    /**
     * @var array<string, FieldMetadata>
     */
    public array $fieldsByColumn;

    /**
     * @param class-string                 $className
     * @param array<string, FieldMetadata> $fields    keyed by property name
     */
    public function __construct(
        public string $className,
        public string $table,
        public ?string $schema,
        public array $fields,
    ) {
        $byColumn = [];
        foreach ($fields as $field) {
            $byColumn[$field->columnName] = $field;
        }

        $this->fieldsByColumn = $byColumn;
    }

    public function hasColumn(string $columnName): bool
    {
        return array_key_exists($columnName, $this->fieldsByColumn);
    }

    /**
     * @return list<string>
     */
    public function identifierColumns(): array
    {
        $columns = [];
        foreach ($this->fields as $field) {
            if (! $field->isIdentifier()) {
                continue;
            }

            $columns[] = $field->columnName;
        }

        return $columns;
    }
}
