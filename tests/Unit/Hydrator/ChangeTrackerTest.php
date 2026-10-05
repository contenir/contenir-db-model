<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Hydrator;

use Contenir\Db\Model\Hydrator\ChangeTracker;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Uses a real EntityHydrator: the tracker's contract is defined in terms of
 * the hydrator's database-form extraction.
 */
#[CoversClass(ChangeTracker::class)]
#[Group('unit')]
final class ChangeTrackerTest extends TestCase
{
    private ChangeTracker $tracker;

    /**
     * @var EntityMetadata<Order>
     */
    private EntityMetadata $metadata;

    private static function order(): Order
    {
        $order           = new Order();
        $order->id       = 1;
        $order->userId   = 9;
        $order->total    = 100;
        $order->placedAt = new DateTimeImmutable('2024-03-04 05:06:07');

        return $order;
    }

    #[Test]
    public function clearStopsTrackingEverything(): void
    {
        $order = self::order();
        $this->tracker->snapshot($this->metadata, $order);

        $this->tracker->clear();

        static::assertFalse($this->tracker->isTracked($order));
    }

    #[Test]
    public function columnInitialisedAfterSnapshotIsAChange(): void
    {
        $order         = new Order();
        $order->userId = 9;
        $order->total  = 100;
        $this->tracker->snapshot($this->metadata, $order);
        $order->placedAt = new DateTimeImmutable('2024-03-04 05:06:07');

        static::assertSame(['placed_at' => '2024-03-04 05:06:07'], $this->tracker->changes($this->metadata, $order));
    }

    #[Test]
    public function equivalentValueObjectsAreNotChanges(): void
    {
        $order = self::order();
        $this->tracker->snapshot($this->metadata, $order);
        $order->placedAt = new DateTimeImmutable('2024-03-04 05:06:07');
        $order->status   = OrderStatus::Pending;

        static::assertSame([], $this->tracker->changes($this->metadata, $order));
    }

    #[Test]
    public function exposesSnapshotValues(): void
    {
        $order = self::order();
        $this->tracker->snapshot($this->metadata, $order);
        $order->total = 999;

        static::assertSame(100, $this->tracker->snapshotOf($order)['total'] ?? null);
    }

    #[Test]
    public function forgetStopsTrackingOneEntity(): void
    {
        $kept      = self::order();
        $forgotten = self::order();
        $this->tracker->snapshot($this->metadata, $kept);
        $this->tracker->snapshot($this->metadata, $forgotten);

        $this->tracker->forget($forgotten);

        static::assertSame(
            [true, false, null],
            [
                $this->tracker->isTracked($kept),
                $this->tracker->isTracked($forgotten),
                $this->tracker->snapshotOf($forgotten),
            ],
        );
    }

    #[Test]
    public function reportsOnlyEditedColumns(): void
    {
        $order = self::order();
        $this->tracker->snapshot($this->metadata, $order);
        $order->total  = 250;
        $order->status = OrderStatus::Shipped;

        static::assertSame(['total' => 250, 'status' => 'shipped'], $this->tracker->changes($this->metadata, $order));
    }

    #[Test]
    public function snapshottedEntityWithoutEditsHasNoChanges(): void
    {
        $order = self::order();
        $this->tracker->snapshot($this->metadata, $order);

        static::assertSame([true, []], [
            $this->tracker->isTracked($order),
            $this->tracker->changes($this->metadata, $order),
        ]);
    }

    #[Test]
    public function untrackedEntityReportsEveryInitialisedColumn(): void
    {
        $order = self::order();

        static::assertSame(
            [
                false,
                [
                    'id'        => 1,
                    'user_id'   => 9,
                    'total'     => 100,
                    'status'    => 'pending',
                    'placed_at' => '2024-03-04 05:06:07',
                ],
            ],
            [$this->tracker->isTracked($order), $this->tracker->changes($this->metadata, $order)],
        );
    }

    protected function setUp(): void
    {
        $this->tracker  = new ChangeTracker(new EntityHydrator(TypeRegistry::withDefaults(), new PropertyAccessor()));
        $this->metadata = (new AttributeMetadataFactory())->getMetadataFor(Order::class);
    }
}
