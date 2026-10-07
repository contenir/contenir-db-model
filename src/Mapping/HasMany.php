<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

use Attribute;
use Override;

/**
 * Related rows whose foreign key points back at this entity.
 *
 * $foreignKey names the column(s) on the target table; $localKey names the
 * column(s) on this table they reference and defaults to this entity's
 * primary key.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HasMany implements RelationInterface
{
    /**
     * @param class-string                $target
     * @param string|list<string>         $foreignKey
     * @param string|list<string>|null    $localKey
     * @param array<string, 'ASC'|'DESC'|'asc'|'desc'> $orderBy
     * @param array<string, scalar|null>  $where
     */
    public function __construct(
        public string $target,
        public string|array $foreignKey,
        public string|array|null $localKey = null,
        public array $orderBy = [],
        public array $where = [],
    ) {}

    #[Override]
    public function orderBy(): array
    {
        return $this->orderBy;
    }

    #[Override]
    public function target(): string
    {
        return $this->target;
    }

    #[Override]
    public function where(): array
    {
        return $this->where;
    }
}
