<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Relation;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\RelationException;
use Contenir\Db\Model\Relation\OwnerPredicate;
use Contenir\Db\Model\Relation\Preloader;
use Contenir\Db\Model\Relation\RelationInitializer;
use Contenir\Db\Model\Relation\RelationLoader;
use Contenir\Db\Model\Relation\RelationSelect;
use Contenir\Db\Model\Relation\RowGroupKey;
use Contenir\Db\Model\Repository;
use ContenirTest\Db\Model\TestAsset\Entity\Album;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\Plain;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\TestAsset\Factory\EntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Preloader::class)]
#[CoversClass(RelationLoader::class)]
#[CoversClass(RelationInitializer::class)]
#[CoversClass(RelationSelect::class)]
#[CoversClass(OwnerPredicate::class)]
#[CoversClass(RowGroupKey::class)]
#[CoversClass(Repository::class)]
#[CoversClass(RelationException::class)]
#[Group('integration')]
final class PreloadTest extends AbstractRelationTestCase
{
    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function siblingPathsSharingAPrefix(): iterable
    {
        yield 'siblings first' => [['orders.user.profile', 'orders.user.tags', 'orders.user']];
        yield 'siblings only' => [['orders.user.profile', 'orders.user.tags']];
        yield 'prefix first' => [['orders.user', 'orders.user.profile', 'orders.user.tags']];
    }

    #[Test]
    public function doesNotWalkNestedPathsWhenNothingWasLoaded(): void
    {
        $withoutOrders = [$this->users()[2]];
        $queries       = $this->queryCount();

        $this->em->getRepository(User::class)->preload($withoutOrders, 'orders.invoices');

        static::assertSame(1, $this->queryCount() - $queries);
    }

    #[Test]
    public function fillsSingleRelationOnEntitiesWithoutTheTrait(): void
    {
        $plain = $this->em->getRepository(Plain::class)->findBy(['id' => 1]);

        $this->em->getRepository(Plain::class)->preload($plain, 'user');

        static::assertSame('Alice', $plain[0]->user->name);
    }

    /**
     * @param list<string> $paths
     */
    #[Test]
    #[DataProvider('siblingPathsSharingAPrefix')]
    public function loadsEverySiblingOfARepeatedPrefix(array $paths): void
    {
        $users   = $this->users();
        $queries = $this->queryCount();

        $this->em->getRepository(User::class)->preload($users, ...$paths);

        static::assertSame(4, $this->queryCount() - $queries);
    }

    #[Test]
    public function loadsHasManyForAllOwnersInOneQuery(): void
    {
        $users   = $this->users();
        $queries = $this->queryCount();

        $this->em->getRepository(User::class)->preload($users, 'orders');

        static::assertSame(
            [1, [[2, 1], [3], []], [true, true, true]],
            [
                $this->queryCount() - $queries,
                [self::ids($users[0]->orders), self::ids($users[1]->orders), self::ids($users[2]->orders)],
                [$users[0]->orders->isLoaded(), $users[1]->orders->isLoaded(), $users[2]->orders->isLoaded()],
            ],
        );
    }

    #[Test]
    public function loadsManyToManyInJoinTableOrderForAllOwnersInOneQuery(): void
    {
        $repository = $this->em->getRepository(Album::class);
        $albums     = $repository->findBy([], ['id' => 'ASC']);
        $queries    = $this->queryCount();

        $repository->preload($albums, 'photos');

        static::assertSame(
            [1, [[3, 2, 1], [1, 3, 2], []]],
            [
                $this->queryCount() - $queries,
                [self::ids($albums[0]->photos), self::ids($albums[1]->photos), self::ids($albums[2]->photos)],
            ],
        );
    }

    #[Test]
    public function loadsNestedPathsAndSingleRelations(): void
    {
        $users   = $this->users();
        $queries = $this->queryCount();

        $this->em->getRepository(User::class)->preload($users, 'orders.user', 'profile', 'tags');
        $loaded = $this->queryCount() - $queries;

        static::assertSame(
            [4, $users[0], 'Hello from Alice', null, [2, 1], [2]],
            [
                $loaded,
                $users[0]->orders->first()?->user,
                $users[0]->profile?->bio,
                $users[1]->profile,
                self::ids($users[0]->tags),
                self::ids($users[1]->tags),
            ],
        );
    }

    #[Test]
    public function nothingToPreloadIsANoOp(): void
    {
        $queries = $this->queryCount();
        $this->em->getRepository(User::class)->preload([], 'orders');
        $this->em->getRepository(User::class)->preload($this->users());
        $afterFind = $queries + 1;

        static::assertSame($afterFind, $this->queryCount());
    }

    #[Test]
    public function ownersWithoutKeysGetEmptyRelationsWithoutQuerying(): void
    {
        $unsaved = EntityFactory::user();
        $queries = $this->queryCount();

        $this->em->getRepository(User::class)->preload([$unsaved], 'orders', 'profile');

        static::assertSame([0, true, null], [
            $this->queryCount() - $queries,
            $unsaved->orders->isEmpty(),
            $unsaved->profile,
        ]);
    }

    #[Test]
    public function rejectsEntitiesOfAnotherClass(): void
    {
        $order = $this->em->getRepository(Order::class)->find(1);

        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('preload() expected entities of ' . User::class . ', got ' . Order::class);

        $this->em->getRepository(User::class)->preload([$order], 'orders');
    }

    #[Test]
    public function rejectsUndeclaredRelation(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('$invoices is not a declared relation');

        $this->em->getRepository(User::class)->preload($this->users(), 'invoices');
    }

    /**
     * @return list<User>
     */
    private function users(): array
    {
        return $this->em->getRepository(User::class)->findBy([], ['id' => 'ASC']);
    }
}
