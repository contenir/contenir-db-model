<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Order;

#[Table('collection_as_array')]
final class CollectionTypedAsArrayEntity
{
    #[Id]
    public int $id;

    /**
     * @var list<Order>
     */
    #[HasMany(Order::class, foreignKey: 'user_id')]
    public array $orders;
}
