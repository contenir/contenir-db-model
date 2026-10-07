<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Order;

#[Table('unknown_order_column')]
final class UnknownOrderColumnEntity
{
    #[Id]
    public int $id;

    /**
     * @var Collection<Order>
     */
    #[HasMany(Order::class, foreignKey: 'user_id', orderBy: ['missing' => 'ASC'])]
    public Collection $orders;
}
