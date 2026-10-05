<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

use Attribute;
use Override;

/**
 * Single related row referenced by a foreign key held on this entity.
 *
 * $foreignKey names the column(s) on this table; $ownerKey names the
 * column(s) on the target table they reference and defaults to the
 * target's primary key.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class BelongsTo implements RelationInterface
{
    /**
     * @param class-string             $target
     * @param string|list<string>      $foreignKey
     * @param string|list<string>|null $ownerKey
     */
    public function __construct(
        public string $target,
        public string|array $foreignKey,
        public string|array|null $ownerKey = null,
    ) {}

    #[Override]
    public function orderBy(): array
    {
        return [];
    }

    #[Override]
    public function target(): string
    {
        return $this->target;
    }

    #[Override]
    public function where(): array
    {
        return [];
    }
}
