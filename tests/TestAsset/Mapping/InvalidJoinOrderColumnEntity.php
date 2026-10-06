<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\ManyToMany;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Via;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;

#[Table('invalid_join_order_column')]
final class InvalidJoinOrderColumnEntity
{
    #[Id]
    public int $id;

    /**
     * @var Collection<Tag>
     */
    #[ManyToMany(
        Tag::class,
        via: new Via('entity_tag', foreignKey: 'entity_id', relatedKey: 'tag_id', orderBy: [
            'entity_tag.sequence' => 'ASC',
        ]),
    )]
    public Collection $tags;
}
