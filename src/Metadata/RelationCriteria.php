<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

/**
 * Fixed filter and ordering applied whenever a relation is loaded.
 *
 * @api
 */
final readonly class RelationCriteria
{
    /**
     * @param array<string, scalar|null>  $where   target column => required value
     * @param array<string, 'ASC'|'DESC'> $orderBy target column => direction
     */
    public function __construct(
        public array $where = [],
        public array $orderBy = [],
    ) {}
}
