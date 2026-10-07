<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Relation;

use Contenir\Db\Model\Collection;
use Contenir\Db\Model\Exception\RelationException;
use Contenir\Db\Model\Relation\LazyRelationsTrait;
use Contenir\Db\Model\Relation\OwnerPredicate;
use Contenir\Db\Model\Relation\RelationBinding;
use Contenir\Db\Model\Relation\RelationInitializer;
use Contenir\Db\Model\Relation\RelationLoader;
use Contenir\Db\Model\Relation\RelationResolver;
use Contenir\Db\Model\Relation\RelationSelect;
use Contenir\Db\Model\Relation\RowGroupKey;
use ContenirTest\Db\Model\TestAsset\Entity\Album;
use ContenirTest\Db\Model\TestAsset\Entity\Membership;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\Plain;
use ContenirTest\Db\Model\TestAsset\Entity\Ticket;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\TestAsset\Factory\EntityFactory;
use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

use function count;

#[CoversClass(RelationInitializer::class)]
#[CoversClass(RelationLoader::class)]
#[CoversClass(RelationSelect::class)]
#[CoversClass(OwnerPredicate::class)]
#[CoversClass(RowGroupKey::class)]
#[CoversClass(RelationResolver::class)]
#[CoversClass(RelationBinding::class)]
#[CoversClass(RelationException::class)]
#[CoversTrait(LazyRelationsTrait::class)]
#[Group('integration')]
final class LazyLoadingTest extends AbstractRelationTestCase
{
    #[Test]
    public function belongsToLoadsTheManagedOwner(): void
    {
        $order = $this->em->getRepository(Order::class)->find(3);

        static::assertSame($this->user(2), $order?->user);
    }

    #[Test]
    public function cloneOfManagedEntityCannotLazyLoad(): void
    {
        $clone = clone $this->user(1);

        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Relation ' . User::class . '::$profile is not loaded');

        $clone->profile;
    }

    #[Test]
    public function collectionAssignedBeforeInsertIsKept(): void
    {
        $user         = EntityFactory::user('new@example.com');
        $user->orders = Collection::of([]);
        $assigned     = $user->orders;
        $this->em->save($user);

        static::assertSame($assigned, $user->orders);
    }

    #[Test]
    public function collectionQueriesOnlyOnce(): void
    {
        $user    = $this->user(1);
        $queries = $this->queryCount();
        $count   = count($user->orders);
        $again   = self::ids($user->orders);

        static::assertSame([2, [2, 1], 1], [$count, $again, $this->queryCount() - $queries]);
    }

    #[Test]
    public function compositeKeyHasManyMatchesEveryKeyColumn(): void
    {
        $membership = $this->em->getRepository(Membership::class)->find(['groupId' => 1, 'userId' => 2]);

        static::assertSame([2, 1], self::ids($membership->permissions ?? []));
    }

    #[Test]
    public function hasManyIsALazyCollectionLoadedInDeclaredOrder(): void
    {
        $user   = $this->user(1);
        $before = $user->orders->isLoaded();

        static::assertSame([false, [2, 1]], [$before, self::ids($user->orders)]);
    }

    #[Test]
    public function hasManyWithoutRowsIsEmpty(): void
    {
        static::assertTrue($this->user(3)->orders->isEmpty());
    }

    #[Test]
    public function hasOneLoadsTargetOrNull(): void
    {
        static::assertSame(['Hello from Alice', null], [$this->user(1)->profile?->bio, $this->user(2)->profile]);
    }

    #[Test]
    public function insertedEntityGetsLazyCollections(): void
    {
        $user = EntityFactory::user('new@example.com');
        $this->em->save($user);

        static::assertSame([true, 0], [$user->orders instanceof Collection, count($user->orders)]);
    }

    /**
     * @mago-expect lint:no-isset Exercises the trait's __isset().
     */
    #[Test]
    public function issetIsFalseWhenTheRelationCannotBeResolved(): void
    {
        $user  = $this->user(1);
        $clone = clone $user;

        static::assertSame([false, false], [isset($clone->profile), isset($user->nonsense)]);
    }

    /**
     * @mago-expect lint:no-isset Exercises the trait's __isset().
     */
    #[Test]
    public function issetLoadsTheRelation(): void
    {
        static::assertSame([true, false], [isset($this->user(1)->profile), isset($this->user(2)->profile)]);
    }

    #[Test]
    public function manyToManyAppliesCriteriaAndOrder(): void
    {
        static::assertSame([2, 1], self::ids($this->user(1)->tags));
    }

    #[Test]
    public function manyToManyOrdersByEveryJoinTableColumn(): void
    {
        static::assertSame(
            [2, 1, 3],
            self::ids($this->em->getRepository(Album::class)->find(1)->photosByPosition ?? []),
        );
    }

    #[Test]
    public function manyToManyOrdersByJoinTableColumnsBeforeTargetColumns(): void
    {
        $albums = $this->em->getRepository(Album::class);

        static::assertSame(
            [[3, 2, 1], [1, 3, 2], []],
            [
                self::ids($albums->find(1)->photos ?? []),
                self::ids($albums->find(2)->photos ?? []),
                self::ids($albums->find(3)->photos ?? []),
            ],
        );
    }

    #[Test]
    public function missingTargetOfNonNullableRelationIsReported(): void
    {
        $ticket = $this->em->getRepository(Ticket::class)->find(2);

        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Relation ' . Ticket::class . '::$user found no related row');

        $ticket?->user;
    }

    #[Test]
    public function refreshDiscardsLoadedRelations(): void
    {
        $user = $this->user(2);
        count($user->orders);
        $this->pdo->exec("INSERT INTO orders VALUES (9, 2, 50, 'pending', '2024-03-01 00:00:00')");

        $this->em->refresh($user);

        static::assertSame([9, 3], self::ids($user->orders));
    }

    #[Test]
    public function refreshPreparesCollectionsAsLazyAgain(): void
    {
        $user = $this->user(2);
        count($user->orders);

        $this->em->refresh($user);

        static::assertFalse($user->orders->isLoaded());
    }

    #[Test]
    public function relationOfEntityBuiltWithNewIsUninitialised(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('must not be accessed before initialization');

        EntityFactory::user()->profile;
    }

    #[Test]
    public function relationsAfterAnAssignedOneStillBecomeLazy(): void
    {
        $user         = EntityFactory::user('new@example.com');
        $user->orders = Collection::of([]);
        $this->em->save($user);

        static::assertFalse($user->tags->isLoaded());
    }

    #[Test]
    public function singleRelationIsStoredOnTheEntityAfterTheFirstRead(): void
    {
        $user    = $this->user(1);
        $queries = $this->queryCount();

        $first  = $user->profile;
        $second = $user->profile;

        static::assertSame([$first, 1], [$second, $this->queryCount() - $queries]);
    }

    #[Test]
    public function singleRelationWithoutTraitMustBePreloaded(): void
    {
        $plain = $this->em->getRepository(Plain::class)->find(1);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('must not be accessed before initialization');

        $plain?->user;
    }

    #[Test]
    public function undefinedPropertyThroughTraitIsReported(): void
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Undefined property ' . User::class . '::$nonsense');

        $this->user(1)->nonsense;
    }

    private function user(int $id): User
    {
        $user = $this->em->getRepository(User::class)->find($id);
        static::assertNotNull($user);

        return $user;
    }
}
