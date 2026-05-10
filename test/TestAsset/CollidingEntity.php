<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Contenir\Db\Model\Entity\AbstractEntity;

/**
 * Has a relation named the same as a column. Constructing should reject this.
 */
class CollidingEntity extends AbstractEntity
{
    protected array $primaryKeys = ['id'];

    protected array $columns = ['id', 'profile'];

    protected array $relations = [
        'profile' => [
            'type'   => AbstractEntity::RELATION_SINGLE,
            'column' => 'id',
            'table'  => [
                'class'  => 'SomeRepository',
                'column' => 'user_id',
            ],
        ],
    ];
}
