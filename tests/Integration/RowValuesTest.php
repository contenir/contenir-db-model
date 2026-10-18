<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\RowValues;
use ContenirTest\Db\Model\TestAsset\Entity\Note;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\Trait\TestDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Entities from rows without the database: new ones, and copies of loaded
 * ones with a row's values in place of their own, converted as a loaded
 * row would be and never managed.
 */
#[CoversClass(RowValues::class)]
#[Group('integration')]
final class RowValuesTest extends TestCase
{
    use TestDatabaseTrait;

    #[Test]
    public function buildsANewEntityFromARowAsALoadedRowWouldBe(): void
    {
        $em = $this->entityManager();

        $order = (new RowValues($em))->fromRow(Order::class, [
            'user_id'   => '1',
            'total'     => '250',
            'status'    => 'shipped',
            'placed_at' => '2025-02-03 04:05:06',
            'unmapped'  => 'ignored',
        ]);

        static::assertSame(
            [null, 1, 250, OrderStatus::Shipped, '2025-02-03 04:05:06', false],
            [
                $order->id,
                $order->userId,
                $order->total,
                $order->status,
                $order->placedAt->format('Y-m-d H:i:s'),
                $em->contains($order),
            ],
        );
    }

    #[Test]
    public function copiesAnEntityWithTheRowsValuesLeavingTheOriginalAndTheDatabaseAlone(): void
    {
        $em   = $this->entityManager();
        $user = $em->getRepository(User::class)->find(1);
        static::assertInstanceOf(User::class, $user);

        $copy = (new RowValues($em))->withRow($user, ['name' => 'Bob', 'created_at' => '2025-02-03 04:05:06']);
        $em->save($user);

        static::assertSame(
            ['Bob', '2025-02-03', 'Alice', '2024-01-01', false, 'Alice'],
            [
                $copy->name,
                $copy->createdAt->format('Y-m-d'),
                $user->name,
                $user->createdAt->format('Y-m-d'),
                $em->contains($copy),
                $this->fetchAll('SELECT name FROM users WHERE id = 1')[0]['name'] ?? null,
            ],
        );
    }

    #[Test]
    public function keepsTheOriginalsRelationsOnTheCopy(): void
    {
        $em   = $this->entityManager();
        $user = $em->getRepository(User::class)->find(1);
        static::assertInstanceOf(User::class, $user);

        $copy = (new RowValues($em))->withRow($user, ['name' => 'Bob']);

        static::assertSame([2, 1], array_map(static fn(Order $order): ?int => $order->id, [...$copy->orders]));
    }

    #[Test]
    public function leavesColumnsTheRowDoesNotNameAsTheyWere(): void
    {
        $em   = $this->entityManager();
        $user = $em->getRepository(User::class)->find(1);
        static::assertInstanceOf(User::class, $user);

        $copy = (new RowValues($em))->withRow($user, ['unmapped' => 'ignored']);

        static::assertSame(['a@example.com', 'Alice'], [$copy->email, $copy->name]);
    }

    #[Test]
    public function refusesARowThatChangesAReadonlyProperty(): void
    {
        $em   = $this->entityManager("INSERT INTO notes VALUES (7, 'Hello', '2024-01-01 00:00:00')");
        $note = $em->getRepository(Note::class)->find(7);
        static::assertInstanceOf(Note::class, $note);

        $this->expectException(HydrationException::class);

        (new RowValues($em))->withRow($note, ['id' => 8]);
    }

    #[Test]
    public function returnsACopyEvenWhenTheRowChangesNothing(): void
    {
        $em   = $this->entityManager();
        $user = $em->getRepository(User::class)->find(1);
        static::assertInstanceOf(User::class, $user);

        static::assertNotSame($user, (new RowValues($em))->withRow($user, []));
    }

    private function entityManager(string ...$seed): EntityManager
    {
        $this->setUpTestDatabase(
            "INSERT INTO users VALUES (1, 'a@example.com', 'Alice', '2024-01-01 00:00:00', 1)",
            "INSERT INTO orders VALUES (1, 1, 100, 'pending', '2024-02-01 00:00:00')",
            "INSERT INTO orders VALUES (2, 1, 300, 'shipped', '2024-02-02 00:00:00')",
            ...$seed,
        );

        return new EntityManager($this->adapter);
    }
}
