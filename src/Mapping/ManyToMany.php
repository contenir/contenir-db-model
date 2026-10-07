<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

use Attribute;
use Override;

/**
 * Related rows linked through an intermediate join table:
 *
 *     #[ManyToMany(Tag::class, via: new Via('user_tag', foreignKey: 'user_id', relatedKey: 'tag_id'))]
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ManyToMany implements RelationInterface
{
    /**
     * @param class-string                $target
     * @param array<string, 'ASC'|'DESC'|'asc'|'desc'> $orderBy
     * @param array<string, scalar|null>  $where
     */
    public function __construct(
        public string $target,
        public Via $via,
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
