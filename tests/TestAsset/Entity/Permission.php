<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

#[Table('permissions', schema: 'crm')]
final class Permission
{
    #[Id]
    public int $id;

    #[Column('group_id')]
    public int $groupId;

    #[Column('user_id')]
    public int $userId;

    #[Column]
    public string $name;
}
