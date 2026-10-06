<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\ManyToMany;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Via;

#[Table('albums')]
final class Album
{
    #[Id]
    public int $id;

    #[Column]
    public string $title;

    /**
     * Ordered by the link's position, then by caption among photos that
     * share a position.
     *
     * @var Collection<Photo>
     */
    #[ManyToMany(
        Photo::class,
        via: new Via('album_photo', foreignKey: 'album_id', relatedKey: 'photo_id', orderBy: ['sequence' => 'desc']),
        orderBy: ['caption' => 'DESC'],
    )]
    public Collection $photos;

    /**
     * Ordered by two join-table columns and nothing on the target.
     *
     * @var Collection<Photo>
     */
    #[ManyToMany(
        Photo::class,
        via: new Via('album_photo', foreignKey: 'album_id', relatedKey: 'photo_id', orderBy: [
            'sequence' => 'ASC',
            'photo_id' => 'DESC',
        ]),
    )]
    public Collection $photosByPosition;
}
