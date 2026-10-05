<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Relation\LazyRelationsTrait;
use DateTimeImmutable;

#[Table('orders')]
final class Order
{
    use LazyRelationsTrait;

    #[Id(generated: true)]
    public ?int $id = null;

    #[Column('user_id')]
    public int $userId;

    #[Column]
    public int $total;

    #[Column]
    public OrderStatus $status = OrderStatus::Pending;

    #[Column('placed_at')]
    public DateTimeImmutable $placedAt;

    #[BelongsTo(User::class, foreignKey: 'user_id')]
    public User $user;
}
