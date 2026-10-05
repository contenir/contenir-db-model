<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Relation;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Metadata\RelationMetadata;
use Contenir\Db\Model\Type\TypeRegistry;

use function serialize;

/**
 * Computes, from a relation row, the key of the owner it belongs to, in
 * the same normalised form as {@see RelationLoader::keyOf()}.
 *
 * @internal
 */
final readonly class RowGroupKey
{
    public function __construct(
        private TypeRegistry $types,
    ) {}

    /**
     * Where each owner key value is read from: the target's foreign-key
     * columns, or the aliased join-table columns.
     *
     * @param EntityMetadata<object> $owner
     * @param EntityMetadata<object> $target
     *
     * @return list<array{FieldMetadata, string}>
     *
     * @throws MappingException
     */
    private static function sources(EntityMetadata $owner, EntityMetadata $target, RelationMetadata $relation): array
    {
        $keys    = $relation->keys;
        $sources = [];
        if (null === $keys->joinTable) {
            foreach ($keys->targetColumns as $column) {
                $sources[] = [$target->getFieldForColumn($column), $column];
            }

            return $sources;
        }

        foreach ($keys->localColumns as $i => $column) {
            $sources[] = [$owner->getFieldForColumn($column), RelationSelect::OWNER_ALIAS . $i];
        }

        return $sources;
    }

    /**
     * @param EntityMetadata<object> $owner
     * @param EntityMetadata<object> $target
     * @param array<string, mixed>   $row
     *
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function of(EntityMetadata $owner, EntityMetadata $target, RelationMetadata $relation, array $row): string
    {
        $tuple = [];
        foreach (self::sources($owner, $target, $relation) as [$field, $column]) {
            $tuple[] = $this->types->toDatabase($field, $this->types->toPhp($field, $row[$column] ?? null));
        }

        return serialize($tuple);
    }
}
