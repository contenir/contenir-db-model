<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Hydrator;

use Contenir\Db\Model\Hydrator\ChangeTracker;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use ContenirTest\Db\Model\Trait\TestDatabaseTrait;
use DateTimeImmutable;
use PhpDb\Sql\Sql;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group('integration')]
final class HydrationRoundTripTest extends TestCase
{
    use TestDatabaseTrait;

    private EntityHydrator $hydrator;

    /**
     * @var EntityMetadata<Order>
     */
    private EntityMetadata $metadata;

    #[Test]
    public function editedEntityWritesBackOnlyChangedColumns(): void
    {
        $tracker = new ChangeTracker($this->hydrator);
        $order   = $this->loadOrder();
        $tracker->snapshot($this->metadata, $order);
        $order->status = OrderStatus::Pending;

        $sql = new Sql($this->adapter, 'orders');
        $sql->prepareStatementForSqlObject(
            $sql->update()->set($tracker->changes($this->metadata, $order))->where(['id' => $order->id]),
        )->execute();

        static::assertSame(OrderStatus::Pending, $this->loadOrder()->status);
    }

    #[Test]
    public function freshlyLoadedEntityHasNoPendingChanges(): void
    {
        $tracker = new ChangeTracker($this->hydrator);
        $order   = $this->loadOrder();
        $tracker->snapshot($this->metadata, $order);

        static::assertSame([], $tracker->changes($this->metadata, $order));
    }

    #[Test]
    public function hydratesEntityFromDriverRow(): void
    {
        $order = $this->loadOrder();

        static::assertEquals(
            [1, 9, 1250, OrderStatus::Shipped, new DateTimeImmutable('2024-03-04 05:06:07')],
            [$order->id, $order->userId, $order->total, $order->status, $order->placedAt],
        );
    }

    protected function setUp(): void
    {
        $this->setUpTestDatabase(
            "INSERT INTO orders (user_id, total, status, placed_at) VALUES (9, 1250, 'shipped', '2024-03-04 05:06:07')",
        );
        $this->hydrator = new EntityHydrator(TypeRegistry::withDefaults());
        $this->metadata = (new AttributeMetadataFactory())->getMetadataFor(Order::class);
    }

    private function loadOrder(): Order
    {
        $sql = new Sql($this->adapter, 'orders');
        $row = $sql->prepareStatementForSqlObject($sql->select()->where(['id' => 1]))
            ->execute()
            ->current();
        static::assertIsArray($row);

        /** @var array<string, mixed> $row */
        return $this->hydrator->hydrate($this->metadata, $row);
    }
}
