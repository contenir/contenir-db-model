<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Order;

#[Table('non_list_keys')]
final class NonListKeysEntity
{
    #[Id]
    public int $id;

    /**
     * @var Collection<Order>
     */
    #[HasMany(Order::class, foreignKey: [3 => 'user_id'], localKey: ['a' => 'id'])]
    public Collection $orders;
}
