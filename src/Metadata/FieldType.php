<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

/**
 * Declared type of a column-mapped property.
 *
 * @api
 */
final readonly class FieldType
{
    /**
     * @param string|null $phpType  declared type name, or null for union/intersection types
     * @param string|null $typeName  explicit converter name from {@see \Contenir\Db\Model\Mapping\Column::$type}
     * @param bool        $sensitive whether values must be kept out of messages and debug output
     */
    public function __construct(
        public ?string $phpType,
        public bool $nullable,
        public ?string $typeName = null,
        public bool $sensitive = false,
    ) {}
}
