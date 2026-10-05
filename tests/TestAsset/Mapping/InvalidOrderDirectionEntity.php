<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Order;

#[Table('invalid_order_direction')]
final class InvalidOrderDirectionEntity
{
    #[Id]
    public int $id;

    /**
     * @var Collection<Order>
     */
    #[HasMany(Order::class, foreignKey: 'user_id', orderBy: ['total' => 'sideways'])]
    public Collection $orders;
}
