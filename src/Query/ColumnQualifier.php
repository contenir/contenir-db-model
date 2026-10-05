<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Query;

use Contenir\Db\Model\Metadata\EntityMetadata;

/**
 * Prefixes column keys with the entity's table name, so predicates and
 * ordering stay unambiguous in selects that join other tables.
 *
 * @internal
 */
final readonly class ColumnQualifier
{
    /**
     * @template T of object
     * @template V
     *
     * @param EntityMetadata<T> $metadata
     * @param array<string, V>  $columns
     *
     * @return array<string, V>
     */
    public static function qualify(EntityMetadata $metadata, array $columns): array
    {
        $qualified = [];
        foreach ($columns as $column => $value) {
            $qualified["{$metadata->table}.{$column}"] = $value;
        }

        return $qualified;
    }
}
