<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Contenir\Db\Model\Entity\AbstractEntity;

class OrderEntity extends AbstractEntity
{
    protected array $primaryKeys = ['id'];

    protected array $columns = ['id', 'user_id', 'total', 'created_at'];

    protected array $relations = [
        'user' => [
            'type'   => AbstractEntity::RELATION_SINGLE,
            'column' => 'user_id',
            'table'  => [
                'class'  => UserRepository::class,
                'column' => 'id',
            ],
        ],
    ];
}
