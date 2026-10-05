<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\HasOne;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\ManyToMany;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Version;
use Contenir\Db\Model\Mapping\Via;
use DateTimeImmutable;

#[Table('users')]
final class User
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Column]
    public string $email;

    #[Column]
    public ?string $name = null;

    #[Column('created_at')]
    public DateTimeImmutable $createdAt;

    #[Version]
    public int $version = 1;

    /**
     * @var iterable<Order>
     */
    #[HasMany(Order::class, foreignKey: 'user_id', orderBy: ['placed_at' => 'desc'])]
    public iterable $orders;

    #[HasOne(Profile::class, foreignKey: 'user_id')]
    public ?Profile $profile;

    /**
     * @var iterable<Tag>
     */
    #[ManyToMany(
        Tag::class,
        via: new Via('user_tag', foreignKey: 'user_id', relatedKey: 'tag_id'),
        orderBy: [
            'name' => 'asc',
        ],
        where: ['active' => true],
    )]
    public iterable $tags;

    public string $transientNote = '';
}
