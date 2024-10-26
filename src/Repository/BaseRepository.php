<?php

namespace Contenir\Db\Model\Repository;

use Contenir\Db\Model\Entity\EntityInterface;
use Contenir\Db\Model\Entity\BaseEntity;
use Laminas\Db\Sql\Select;

class BaseRepository extends AbstractRepository
{
    /**
     * @param iterable $data
     *
     * @return EntityInterface
     */
    public function create(iterable $data = []): EntityInterface
    {
        return new BaseEntity($data);
    }

    /**
     * @param null        $where
     * @param null        $order
     * @param Select|null $select
     *
     * @return EntityInterface|null
     */
    public function findOne($where = null, $order = null, Select $select = null): ?EntityInterface
    {
        return $this->find($where, $order, $select)->current();
    }
}
