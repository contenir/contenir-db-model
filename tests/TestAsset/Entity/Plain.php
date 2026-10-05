<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

/**
 * Single relation without LazyRelationsTrait: only preload() fills it.
 */
#[Table('tickets')]
final class Plain
{
    #[Id]
    public int $id;

    #[Column('user_id')]
    public int $userId;

    #[BelongsTo(User::class, foreignKey: 'user_id')]
    public User $user;
}
