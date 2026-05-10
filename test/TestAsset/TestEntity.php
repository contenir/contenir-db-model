<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Contenir\Db\Model\Entity\AbstractEntity;

class TestEntity extends AbstractEntity
{
    protected array $primaryKeys = ['id'];

    protected array $columns = [
        'id',
        'name',
        'email',
    ];

    protected array $relations = [
        'profile' => [
            'type'   => AbstractEntity::RELATION_SINGLE,
            'column' => 'id',
            'table'  => [
                'class'  => 'ProfileRepository',
                'column' => 'user_id',
            ],
        ],
    ];
}
