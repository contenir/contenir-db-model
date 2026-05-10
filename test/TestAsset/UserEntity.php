<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Contenir\Db\Model\Entity\AbstractEntity;

class UserEntity extends AbstractEntity
{
    protected array $primaryKeys = ['id'];

    protected array $columns = ['id', 'email', 'name'];

    protected array $relations = [
        'profile' => [
            'type'   => AbstractEntity::RELATION_SINGLE,
            'column' => 'id',
            'table'  => [
                'class'  => ProfileRepository::class,
                'column' => 'user_id',
            ],
        ],
        'orders' => [
            'type'   => AbstractEntity::RELATION_MANY,
            'column' => 'id',
            'table'  => [
                'class'  => OrderRepository::class,
                'column' => 'user_id',
            ],
            'order'  => ['created_at DESC'],
        ],
        'tags' => [
            'type'   => AbstractEntity::RELATION_MANY,
            'column' => 'id',
            'table'  => [
                'class'  => TagRepository::class,
                'column' => 'id',
            ],
            'via' => [
                'table'  => 'user_tag',
                'column' => 'user_id',
                'join'   => 'tag_id',
            ],
        ],
    ];
}
