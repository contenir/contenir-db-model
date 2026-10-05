<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Relation\LazyRelationsTrait;

/**
 * Non-nullable BelongsTo whose foreign key may dangle in tests.
 */
#[Table('tickets')]
final class Ticket
{
    use LazyRelationsTrait;

    #[Id]
    public int $id;

    #[Column('user_id')]
    public int $userId;

    #[BelongsTo(User::class, foreignKey: 'user_id')]
    public User $user;
}
