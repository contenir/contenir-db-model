<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;

#[Table('custom_relation')]
final class CustomRelationEntity
{
    #[Id]
    public int $id;

    #[CustomRelation]
    public Tag $tag;
}
