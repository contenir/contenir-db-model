<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\HasOne;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Profile;

#[Table('relation_default')]
final class RelationWithDefaultEntity
{
    #[Id]
    public int $id;

    #[HasOne(Profile::class, foreignKey: 'user_id')]
    public ?Profile $profile = null;
}
