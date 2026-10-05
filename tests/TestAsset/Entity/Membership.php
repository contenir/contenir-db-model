<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

/**
 * Composite natural key, schema-qualified table, readonly and
 * explicitly-converted columns.
 */
#[Table('memberships', schema: 'crm')]
final class Membership
{
    /**
     * @param array<string, mixed>|string $meta
     */
    public function __construct(
        #[Id]
        #[Column('group_id')]
        public readonly int $groupId,
        #[Id]
        #[Column('user_id')]
        public readonly int $userId,
        #[Column]
        public readonly string $role,
        #[Column(type: 'json')]
        public array|string $meta = [],
    ) {}

    /**
     * @var Collection<Permission>
     */
    #[HasMany(
        Permission::class,
        foreignKey: ['group_id', 'user_id'],
        localKey: ['group_id', 'user_id'],
        orderBy: [
            'name' => 'ASC',
        ],
    )]
    public Collection $permissions;
}
