<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Closure;
use Contenir\Db\Model\Entity\EntityInterface;
use Contenir\Db\Model\Repository\AbstractRepository;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\TableIdentifier;

class OrderRepository extends AbstractRepository
{
    protected TableIdentifier|string|array|null $table = 'orders';

    public function create(iterable $data = []): EntityInterface
    {
        return new OrderEntity($data);
    }

    /**
     * @param Closure|array|string|int|null $where
     * @param array|string|null              $order
     */
    public function findOne($where = null, $order = null, ?Select $select = null): ?EntityInterface
    {
        return $this->find($where, $order, $select)->current();
    }
}
