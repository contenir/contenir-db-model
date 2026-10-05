<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\ManyToMany;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Via;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;

#[Table('join_key_mismatch')]
final class JoinKeyMismatchEntity
{
    #[Id]
    public int $id;

    /**
     * @var Collection<Tag>
     */
    #[ManyToMany(Tag::class, via: new Via('entity_tag', foreignKey: ['entity_id', 'region'], relatedKey: 'tag_id'))]
    public Collection $tags;
}
