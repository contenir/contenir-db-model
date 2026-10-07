<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\ManyToMany;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Via;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;
use ContenirTest\Db\Model\TestAsset\Entity\User;

#[Table('explicit_keys')]
final class ExplicitKeysEntity
{
    #[Id]
    public int $id;

    #[Column]
    public string $code;

    #[Column('user_email')]
    public string $userEmail;

    #[BelongsTo(User::class, foreignKey: 'user_email', ownerKey: 'email')]
    public User $user;

    /**
     * @var Collection<Tag>
     */
    #[ManyToMany(Tag::class, via: new Via(
        'explicit_tag',
        foreignKey: 'entity_code',
        relatedKey: 'tag_name',
        localKey: 'code',
        targetKey: 'name',
    ))]
    public Collection $tags;
}
