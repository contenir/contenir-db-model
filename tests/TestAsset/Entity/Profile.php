<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

#[Table('profiles')]
final class Profile
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Column('user_id')]
    public int $userId;

    #[Column]
    public string $bio = '';
}
