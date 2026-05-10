<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Repository;

use Closure;
use Contenir\Db\Model\Entity\AbstractEntity;
use Contenir\Db\Model\Entity\BaseEntity;
use Contenir\Db\Model\Entity\EntityInterface;
use Contenir\Db\Model\Exception\RuntimeException;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;

use function sprintf;

class BaseRepository extends AbstractRepository
{
    public function __construct(
        Adapter $adapter,
        AbstractEntity $entityPrototype,
        RepositoryLookup $repositoryLookup
    ) {
        if ($this->table === null) {
            throw new RuntimeException(sprintf(
                '%s requires a non-null $table; instantiate a subclass that declares one',
                static::class
            ));
        }

        parent::__construct($adapter, $entityPrototype, $repositoryLookup);
    }

    public function create(iterable $data = []): EntityInterface
    {
        return new BaseEntity($data);
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
