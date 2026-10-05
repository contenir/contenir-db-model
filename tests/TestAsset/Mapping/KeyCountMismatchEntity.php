<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use ContenirTest\Db\Model\TestAsset\Entity\Order;

#[Table('key_count_mismatch')]
final class KeyCountMismatchEntity
{
    #[Id]
    public int $id;

    #[Column]
    public int $region;

    /**
     * @var iterable<Order>
     */
    #[HasMany(Order::class, foreignKey: 'user_id', localKey: ['id', 'region'])]
    public iterable $orders;
}
