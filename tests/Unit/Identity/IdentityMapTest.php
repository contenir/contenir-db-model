<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Identity;

use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Identity\IdentityMap;
use ContenirTest\Db\Model\TestAsset\Entity\Membership;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentityMap::class)]
#[CoversClass(IdentityConflictException::class)]
#[Group('unit')]
final class IdentityMapTest extends TestCase
{
    private IdentityMap $map;

    #[Test]
    public function addingSameInstanceAgainIsANoOp(): void
    {
        $user = new User();
        $this->map->add(User::class, ['id' => 1], $user);
        $this->map->add(User::class, ['id' => 1], $user);

        static::assertSame(1, $this->map->count());
    }

    #[Test]
    public function clearForgetsEverything(): void
    {
        $user = new User();
        $this->map->add(User::class, ['id' => 1], $user);

        $this->map->clear();

        static::assertSame([0, false], [$this->map->count(), $this->map->contains($user)]);
    }

    #[Test]
    public function reAddingUnderNewIdentifierMovesTheEntry(): void
    {
        $user = new User();
        $this->map->add(User::class, ['id' => 1], $user);
        $this->map->add(User::class, ['id' => 2], $user);

        static::assertSame(
            [null, $user, ['id' => 2], 1],
            [
                $this->map->get(User::class, ['id' => 1]),
                $this->map->get(User::class, ['id' => 2]),
                $this->map->identifierOf($user),
                $this->map->count(),
            ],
        );
    }

    #[Test]
    public function rejectsSecondInstanceForSameIdentifier(): void
    {
        $this->map->add(User::class, ['id' => 1], new User());

        $this->expectException(IdentityConflictException::class);
        $this->expectExceptionMessage('Another instance of "' . User::class . '" with identifier {"id":1}');

        $this->map->add(User::class, ['id' => 1], new User());
    }

    #[Test]
    public function removeDropsOnlyThatEntity(): void
    {
        $removed = new User();
        $kept    = new User();
        $this->map->add(User::class, ['id' => 1], $removed);
        $this->map->add(User::class, ['id' => 2], $kept);

        $this->map->remove($removed);
        $this->map->remove(new User());

        static::assertSame(
            [false, null, true, 1],
            [
                $this->map->contains($removed),
                $this->map->get(User::class, ['id' => 1]),
                $this->map->contains($kept),
                $this->map->count(),
            ],
        );
    }

    #[Test]
    public function reportsMembershipAndIdentifierOfEntities(): void
    {
        $managed = new User();
        $this->map->add(User::class, ['id' => 1], $managed);
        $unmanaged = new User();

        static::assertSame(
            [true, ['id' => 1], false, null],
            [
                $this->map->contains($managed),
                $this->map->identifierOf($managed),
                $this->map->contains($unmanaged),
                $this->map->identifierOf($unmanaged),
            ],
        );
    }

    #[Test]
    public function returnsNullForUnknownIdentifier(): void
    {
        static::assertNull($this->map->get(User::class, ['id' => 1]));
    }

    #[Test]
    public function returnsRegisteredInstanceForIdentifier(): void
    {
        $user = new User();
        $this->map->add(User::class, ['id' => 1], $user);

        static::assertSame($user, $this->map->get(User::class, ['id' => 1]));
    }

    #[Test]
    public function separatesIdentitiesByClass(): void
    {
        $this->map->add(User::class, ['id' => 1], new User());

        static::assertNull($this->map->get(Order::class, ['id' => 1]));
    }

    #[Test]
    public function supportsCompositeIdentifiers(): void
    {
        $membership = new Membership(1, 2, 'owner');
        $this->map->add(Membership::class, ['group_id' => 1, 'user_id' => 2], $membership);

        static::assertSame(
            [$membership, null],
            [
                $this->map->get(Membership::class, ['group_id' => 1, 'user_id' => 2]),
                $this->map->get(Membership::class, ['group_id' => 2, 'user_id' => 1]),
            ],
        );
    }

    protected function setUp(): void
    {
        $this->map = new IdentityMap();
    }
}
