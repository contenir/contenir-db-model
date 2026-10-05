<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

/**
 * Normalised description of one column-mapped property.
 *
 * @api
 */
final readonly class FieldMetadata
{
    public function __construct(
        public string $propertyName,
        public string $columnName,
        public FieldType $type,
        public FieldRole $role = FieldRole::Column,
        public bool $readonly = false,
    ) {}

    public function isIdentifier(): bool
    {
        return $this->role->isIdentifier();
    }
}
