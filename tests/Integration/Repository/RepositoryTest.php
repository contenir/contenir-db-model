<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Repository;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\QueryException;
use Contenir\Db\Model\Persistence\RowFetcher;
use Contenir\Db\Model\Query\ColumnQualifier;
use Contenir\Db\Model\Query\CriteriaTranslator;
use Contenir\Db\Model\Query\EntityReader;
use Contenir\Db\Model\Query\QueryContext;
use Contenir\Db\Model\Repository;
use ContenirTest\Db\Model\TestAsset\Entity\Membership;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\TestAsset\Repository\UserRepository;
use ContenirTest\Db\Model\Trait\TestDatabaseTrait;
use DateTimeImmutable;
use PhpDb\Sql\Select;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function iterator_to_array;

#[CoversClass(Repository::class)]
#[CoversClass(EntityReader::class)]
#[CoversClass(RowFetcher::class)]
#[CoversClass(CriteriaTranslator::class)]
#[CoversClass(ColumnQualifier::class)]
#[CoversClass(QueryContext::class)]
#[CoversClass(EntityManager::class)]
#[CoversClass(QueryException::class)]
#[Group('integration')]
final class RepositoryTest extends TestCase
{
    use TestDatabaseTrait;

    private EntityManager $em;

    /**
     * @return array<string, array{class-string, int|string|array<string, mixed>, string}>
     */
    public static function invalidIdentifierProvider(): array
    {
        return [
            'scalar for composite key' => [Membership::class, 1, 'an array with keys [groupId, userId]'],
            'missing key property'     => [Membership::class, ['groupId' => 1], 'an array with keys [groupId, userId]'],
            'extra key property'       => [
                Membership::class,
                ['groupId' => 1, 'userId' => 2, 'role' => 'x'],
                'keys [groupId, userId]',
            ],
            'array without the key'    => [User::class, ['email' => 'a@example.com'], 'a scalar value for $id'],
        ];
    }

    /**
     * @param list<object> $entities
     *
     * @return list<int|null>
     */
    private static function ids(array $entities): array
    {
        return array_map(static fn(object $entity): ?int => $entity instanceof User || $entity instanceof Order
            ? $entity->id
            : null, $entities);
    }

    #[Test]
    public function countsAllOrMatchingRows(): void
    {
        $orders = $this->em->getRepository(Order::class);

        static::assertSame([3, 2], [$orders->count(), $orders->count(['status' => OrderStatus::Shipped])]);
    }

    #[Test]
    public function customSelectsAreHydratedAndShareIdentities(): void
    {
        $users  = new UserRepository($this->em);
        $byMail = $users->findByEmailDomain('example.com');

        static::assertSame([[1, 3], true], [self::ids($byMail), $byMail[0] === $users->find(1)]);
    }

    #[Test]
    public function customSelectWithoutPrimaryKeyIsRejected(): void
    {
        $users  = $this->em->getRepository(User::class);
        $select = (new Select('users'))->columns(['email']);

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('include the primary key in the select');

        $users->fetch($select);
    }

    #[Test]
    public function fetchOneRunsCustomSelect(): void
    {
        $users  = $this->em->getRepository(User::class);
        $select = $users->createSelect()->where(['email' => 'b@test.org']);

        static::assertSame(2, $users->fetchOne($select)?->id);
    }

    #[Test]
    public function findByAcceptsBackingValuesListsAndNull(): void
    {
        $orders = $this->em->getRepository(Order::class);
        $users  = $this->em->getRepository(User::class);

        static::assertSame(
            [[2, 3], [1, 3], [2]],
            [
                self::ids($orders->findBy(['status' => 'shipped'])),
                self::ids($orders->findBy(['id' => [1, 3]])),
                self::ids($users->findBy(['name' => null])),
            ],
        );
    }

    #[Test]
    public function findByAppliesLimitAndOffset(): void
    {
        $users = $this->em->getRepository(User::class)->findBy([], ['id' => 'ASC'], limit: 1, offset: 1);

        static::assertSame([2], self::ids($users));
    }

    #[Test]
    public function findByConvertsDateCriteria(): void
    {
        $users = $this->em
            ->getRepository(User::class)
            ->findBy(['createdAt' => new DateTimeImmutable('2024-01-02 00:00:00')]);

        static::assertSame([2], self::ids($users));
    }

    #[Test]
    public function findByConvertsEnumCriteriaAndOrders(): void
    {
        $orders = $this->em->getRepository(Order::class)->findBy(['status' => OrderStatus::Shipped], [
            'total' => 'desc',
        ]);

        static::assertSame([2, 3], self::ids($orders));
    }

    #[Test]
    public function findByWithoutArgumentsReturnsEveryRow(): void
    {
        static::assertCount(3, $this->em->getRepository(User::class)->findBy());
    }

    #[Test]
    public function findersApplyCriteriaAndOrderOnTopOfAJoinedSelect(): void
    {
        $users  = $this->em->getRepository(User::class);
        $select = $users->createSelect()
            ->join('orders', 'orders.user_id = users.id', [])
            ->where(['orders.status' => 'shipped']);

        static::assertSame(
            [[3], [1, 3], 1, [1, 3]],
            [
                self::ids($users->findBy(['name' => 'Cara'], ['id' => 'ASC'], select: $select)),
                self::ids($users->findBy([], ['id' => 'ASC'], select: $select)),
                $users->findOneBy([], ['id' => 'ASC'], $select)?->id,
                self::ids(iterator_to_array($users->stream([], ['id' => 'ASC'], $select), preserve_keys: false)),
            ],
        );
    }

    #[Test]
    public function findOneByReturnsFirstMatchInOrder(): void
    {
        $order = $this->em->getRepository(Order::class)->findOneBy(['userId' => 1], ['placedAt' => 'DESC']);

        static::assertSame(2, $order?->id);
    }

    #[Test]
    public function findReturnsManagedInstanceWithoutQuerying(): void
    {
        $users = $this->em->getRepository(User::class);
        $first = $users->find(1);
        $this->pdo->exec('DELETE FROM users');

        static::assertSame($first, $users->find(1));
    }

    #[Test]
    public function findReturnsNullForMissingRow(): void
    {
        static::assertNull($this->em->getRepository(User::class)->find(99));
    }

    #[Test]
    public function findsByCompositeKeyKeyedByProperty(): void
    {
        $membership = $this->em->getRepository(Membership::class)->find(['groupId' => 1, 'userId' => '2']);

        static::assertSame('owner', $membership?->role);
    }

    #[Test]
    public function findsByScalarPrimaryKeyInEitherRepresentation(): void
    {
        $users = $this->em->getRepository(User::class);

        static::assertSame(['Alice', 'Cara'], [$users->find(1)?->name, $users->find('3')?->name]);
    }

    #[Test]
    public function findWithSelectQueriesEvenWhenEntityIsManaged(): void
    {
        $users   = $this->em->getRepository(User::class);
        $managed = $users->find(1);
        $alice   = $users->createSelect()->where(['users.name' => 'Alice']);
        $bob     = $users->createSelect()->where(['users.name' => 'Bob']);

        static::assertSame([$managed, null], [$users->find(1, $alice), $users->find(1, $bob)]);
    }

    #[Test]
    public function findWithSelectSupportsCompositeKeysOnSchemaQualifiedTables(): void
    {
        $memberships = $this->em->getRepository(Membership::class);

        static::assertSame(
            'owner',
            $memberships->find(['groupId' => 1, 'userId' => 2], $memberships->createSelect())?->role,
        );
    }

    #[Test]
    public function getRepositoryReturnsOneInstancePerClass(): void
    {
        static::assertSame($this->em->getRepository(User::class), $this->em->getRepository(User::class));
    }

    #[Test]
    public function givenSelectIsNotModified(): void
    {
        $users  = $this->em->getRepository(User::class);
        $select = $users->createSelect();

        $users->findBy(['name' => 'Alice'], ['id' => 'DESC'], limit: 1, select: $select);

        static::assertCount(3, $users->fetch($select));
    }

    #[Test]
    public function loadedEntitiesAreManagedForSaving(): void
    {
        $user = $this->em->getRepository(User::class)->find(2);
        static::assertNotNull($user);
        $user->name = 'Bea';

        $this->em->save($user);

        static::assertSame(
            [['name' => 'Bea', 'version' => 2]],
            $this->fetchAll('SELECT name, version FROM users WHERE id = 2'),
        );
    }

    #[Test]
    public function rejectsInvalidOrderDirection(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('direction must be ASC or DESC');

        $this->em->getRepository(Order::class)->findBy([], ['total' => 'sideways']);
    }

    /**
     * @param class-string                    $className
     * @param int|string|array<string, mixed> $id
     */
    #[DataProvider('invalidIdentifierProvider')]
    #[Test]
    public function rejectsMalformedIdentifier(string $className, int|string|array $id, string $message): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage($message);

        $this->em->getRepository($className)->find($id);
    }

    #[Test]
    public function rejectsUnknownCriteriaProperty(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('"user_id" is not a mapped column property');

        $this->em->getRepository(Order::class)->findBy(['user_id' => 1]);
    }

    #[Test]
    public function streamYieldsEntitiesLazily(): void
    {
        $stream = $this->em->getRepository(Order::class)->stream(['userId' => 1], ['id' => 'ASC']);

        static::assertSame([1, 2], self::ids(iterator_to_array($stream, preserve_keys: false)));
    }

    protected function setUp(): void
    {
        $this->setUpTestDatabase(
            "INSERT INTO users VALUES (1, 'a@example.com', 'Alice', '2024-01-01 00:00:00', 1)",
            "INSERT INTO users VALUES (2, 'b@test.org', NULL, '2024-01-02 00:00:00', 1)",
            "INSERT INTO users VALUES (3, 'c@example.com', 'Cara', '2024-01-03 00:00:00', 1)",
            "INSERT INTO orders VALUES (1, 1, 100, 'pending', '2024-02-01 00:00:00')",
            "INSERT INTO orders VALUES (2, 1, 300, 'shipped', '2024-02-02 00:00:00')",
            "INSERT INTO orders VALUES (3, 3, 200, 'shipped', '2024-02-03 00:00:00')",
            "INSERT INTO crm.memberships VALUES (1, 2, 'owner', '{}')",
        );
        $this->em = new EntityManager($this->adapter);
    }
}
