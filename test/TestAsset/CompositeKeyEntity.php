<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Contenir\Db\Model\Entity\AbstractEntity;

class CompositeKeyEntity extends AbstractEntity
{
    protected array $primaryKeys = ['tenant_id', 'user_id'];

    protected array $columns = [
        'tenant_id',
        'user_id',
        'role',
    ];
}
