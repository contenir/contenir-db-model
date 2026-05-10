<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Contenir\Db\Model\Entity\AbstractEntity;

class TagEntity extends AbstractEntity
{
    protected array $primaryKeys = ['id'];

    protected array $columns = ['id', 'label'];
}
