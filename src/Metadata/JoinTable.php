<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

/**
 * Intermediate table of a many-to-many relation.
 *
 * @api
 */
final readonly class JoinTable
{
    /**
     * @param list<string> $localColumns  join-table columns referencing the owning entity
     * @param list<string> $targetColumns join-table columns referencing the target entity
     */
    public function __construct(
        public string $table,
        public array $localColumns,
        public array $targetColumns,
    ) {}
}
