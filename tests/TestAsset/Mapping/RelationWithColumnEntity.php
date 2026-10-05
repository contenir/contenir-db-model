<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\User;

#[Table('relation_with_column')]
final class RelationWithColumnEntity
{
    #[Id]
    public int $id;

    #[Column('user_id')]
    #[BelongsTo(User::class, foreignKey: 'user_id')]
    public User $user;
}
