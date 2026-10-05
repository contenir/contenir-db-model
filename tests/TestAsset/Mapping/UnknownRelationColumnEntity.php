<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Order;

#[Table('unknown_relation_column')]
final class UnknownRelationColumnEntity
{
    #[Id]
    public int $id;

    /**
     * @var iterable<Order>
     */
    #[HasMany(Order::class, foreignKey: 'owner_id')]
    public iterable $orders;
}
