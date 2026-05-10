<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Contenir\Db\Model\Entity\EntityInterface;
use Contenir\Db\Model\Repository\AbstractRepository;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\TableIdentifier;

class TestRepository extends AbstractRepository
{
    protected TableIdentifier|string|array|null $table = 'users';

    public function create(iterable $data = []): EntityInterface
    {
        return new TestEntity($data);
    }

    public function findOne($where = null, $order = null, Select $select = null): ?EntityInterface
    {
        $result = $this->find($where, $order, $select);
        return $result->current();
    }
}
