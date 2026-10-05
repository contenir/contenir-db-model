<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Profile;
use ContenirTest\Db\Model\TestAsset\Entity\User;

#[Table('wrong_target_type')]
final class SingleRelationWrongTypeEntity
{
    #[Id]
    public int $id;

    #[Column('user_id')]
    public int $userId;

    #[BelongsTo(User::class, foreignKey: 'user_id')]
    public Profile $user;
}
