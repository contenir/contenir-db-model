<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasOne;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Profile;

#[Table('multiple_relations')]
final class MultipleRelationsEntity
{
    #[Id]
    public int $id;

    #[Column('profile_id')]
    public int $profileId;

    #[BelongsTo(Profile::class, foreignKey: 'profile_id')]
    #[HasOne(Profile::class, foreignKey: 'user_id')]
    public Profile $profile;
}
