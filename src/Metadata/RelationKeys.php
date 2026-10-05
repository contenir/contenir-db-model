<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

/**
 * Key columns of a relation, expressed from the database's point of view:
 * $localColumns live on the owning entity's table and $targetColumns on
 * the target entity's table, paired by position. For many-to-many
 * relations the pairing goes through $joinTable.
 *
 * @api
 */
final readonly class RelationKeys
{
    /**
     * @param list<string> $localColumns
     * @param list<string> $targetColumns
     */
    public function __construct(
        public array $localColumns,
        public array $targetColumns,
        public ?JoinTable $joinTable = null,
    ) {}
}
