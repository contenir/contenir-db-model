<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Contenir\Db\Model\Repository\BaseRepository;
use Laminas\Db\Sql\TableIdentifier;

class ConcreteBaseRepository extends BaseRepository
{
    protected TableIdentifier|string|array|null $table = 'unused';
}
