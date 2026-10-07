<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Hydrator;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Membership;
use ContenirTest\Db\Model\TestAsset\Entity\Note;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TypeRegistry and the metadata factory are used for real: both are final,
 * side-effect-free value producers, and doubling them would only restate
 * their behaviour.
 */
#[CoversClass(EntityHydrator::class)]
#[CoversClass(HydrationException::class)]
#[Group('unit')]
final class EntityHydratorTest extends TestCase
{
    private EntityHydrator $hydrator;

    private AttributeMetadataFactory $metadata;

    #[Test]
    public function assignsSingleProperty(): void
    {
        $user = new User();
        $this->hydrator->assign($user, 'id', 42);

        static::assertSame(42, $user->id);
    }

    #[Test]
    public function bypassesConstructorAndFillsPrivateAndInheritedReadonlyProperties(): void
    {
        $note = $this->hydrator->hydrate($this->metadata->getMetadataFor(Note::class), [
            'id'         => 3,
            'body'       => 'text',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        static::assertSame([3, 'text'], [$note->id, $note->body()]);
    }

    #[Test]
    public function extractsInitialisedPropertiesAsDatabaseValuesKeyedByColumn(): void
    {
        $order         = new Order();
        $order->userId = 9;
        $order->total  = 1250;

        static::assertSame(
            ['id' => null, 'user_id' => 9, 'total' => 1250, 'status' => 'pending'],
            $this->hydrator->extract($this->metadata->getMetadataFor(Order::class), $order),
        );
    }

    #[Test]
    public function extractsLaterColumnsAfterAnUninitialisedOne(): void
    {
        $order        = new Order();
        $order->total = 1250;

        static::assertSame(
            ['id' => null, 'total' => 1250, 'status' => 'pending'],
            $this->hydrator->extract($this->metadata->getMetadataFor(Order::class), $order),
        );
    }

    #[Test]
    public function hydratesConvertedValuesIntoPropertiesByColumn(): void
    {
        $order = $this->hydrator->hydrate($this->metadata->getMetadataFor(Order::class), [
            'id'        => '5',
            'user_id'   => 9,
            'total'     => '1250',
            'status'    => 'shipped',
            'placed_at' => '2024-03-04 05:06:07',
        ]);

        static::assertEquals(
            [5, 9, 1250, OrderStatus::Shipped, new DateTimeImmutable('2024-03-04 05:06:07')],
            [$order->id, $order->userId, $order->total, $order->status, $order->placedAt],
        );
    }

    #[Test]
    public function hydratesLaterColumnsAfterOneMissingFromRow(): void
    {
        $order = $this->hydrator->hydrate($this->metadata->getMetadataFor(Order::class), [
            'id'     => 5,
            'total'  => 1250,
            'status' => 'shipped',
        ]);

        static::assertSame([5, 1250, OrderStatus::Shipped], [$order->id, $order->total, $order->status]);
    }

    #[Test]
    public function leavesDefaultsForColumnsMissingFromRowAndIgnoresUnmappedColumns(): void
    {
        $user = $this->hydrator->hydrate($this->metadata->getMetadataFor(User::class), [
            'id'       => 1,
            'email'    => 'a@example.com',
            'unmapped' => 'ignored',
        ]);

        static::assertSame([null, 1, ''], [$user->name, $user->version, $user->transientNote]);
    }

    #[Test]
    public function refreshAcceptsUnchangedReadonlyAndInitialisesUnsetReadonly(): void
    {
        $metadata   = $this->metadata->getMetadataFor(Membership::class);
        $membership = $this->hydrator->hydrate($metadata, ['group_id' => 1, 'user_id' => 2]);

        $this->hydrator->refresh($metadata, $membership, ['group_id' => '1', 'user_id' => 2, 'role' => 'owner']);

        static::assertSame([1, 2, 'owner'], [$membership->groupId, $membership->userId, $membership->role]);
    }

    #[Test]
    public function refreshesLaterColumnsAfterOneMissingFromRow(): void
    {
        $metadata = $this->metadata->getMetadataFor(Order::class);
        $order    = $this->hydrator->hydrate($metadata, [
            'id'      => 5,
            'user_id' => 9,
            'total'   => 1,
            'status'  => 'pending',
        ]);

        $this->hydrator->refresh($metadata, $order, ['id' => 5, 'total' => 2, 'status' => 'shipped']);

        static::assertSame([9, 2, OrderStatus::Shipped], [$order->userId, $order->total, $order->status]);
    }

    #[Test]
    public function refreshOverwritesMutableProperties(): void
    {
        $metadata = $this->metadata->getMetadataFor(User::class);
        $user     = $this->hydrator->hydrate($metadata, ['id' => 1, 'email' => 'old@example.com', 'version' => 1]);

        $this->hydrator->refresh($metadata, $user, ['email' => 'new@example.com', 'version' => 2, 'other' => 'x']);

        static::assertSame(['new@example.com', 2], [$user->email, $user->version]);
    }

    #[Test]
    public function refreshRejectsChangedReadonlyValue(): void
    {
        $metadata   = $this->metadata->getMetadataFor(Membership::class);
        $membership = $this->hydrator->hydrate($metadata, ['group_id' => 1, 'user_id' => 2, 'role' => 'owner']);

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Cannot refresh readonly property ' . Membership::class . '::$role');

        $this->hydrator->refresh($metadata, $membership, ['role' => 'member']);
    }

    protected function setUp(): void
    {
        $this->hydrator = new EntityHydrator(TypeRegistry::withDefaults(), new PropertyAccessor());
        $this->metadata = new AttributeMetadataFactory();
    }
}
