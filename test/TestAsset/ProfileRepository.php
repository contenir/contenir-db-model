<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Contenir\Db\Model\Entity\EntityInterface;
use Contenir\Db\Model\Repository\AbstractRepository;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\TableIdentifier;

class ProfileRepository extends AbstractRepository
{
    protected TableIdentifier|string|array|null $table = 'profiles';

    public function create(iterable $data = []): EntityInterface
    {
        return new ProfileEntity($data);
    }

    public function findOne($where = null, $order = null, Select $select = null): ?EntityInterface
    {
        return $this->find($where, $order, $select)->current();
    }
}
