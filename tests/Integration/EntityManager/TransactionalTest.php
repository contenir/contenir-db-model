<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\EntityManager;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\StaleEntityException;
use Contenir\Db\Model\Persistence\EntityPersister;
use Contenir\Db\Model\Persistence\EntityRefresher;
use Contenir\Db\Model\Persistence\EntityRemover;
use Contenir\Db\Model\Persistence\JournalEntry;
use Contenir\Db\Model\Persistence\RowFetcher;
use Contenir\Db\Model\Persistence\Session;
use Contenir\Db\Model\Persistence\StatementRunner;
use Contenir\Db\Model\Persistence\TransactionManager;
use Contenir\Db\Model\Persistence\VersionLock;
use Contenir\Db\Model\Persistence\WriteJournal;
use ContenirTest\Db\Model\TestAsset\Db\Schema;
use ContenirTest\Db\Model\TestAsset\Factory\EntityFactory;
use ContenirTest\Db\Model\Trait\TestDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

use function count;

#[CoversClass(EntityManager::class)]
#[CoversClass(EntityPersister::class)]
#[CoversClass(EntityRemover::class)]
#[CoversClass(EntityRefresher::class)]
#[CoversClass(RowFetcher::class)]
#[CoversClass(Session::class)]
#[CoversClass(StatementRunner::class)]
#[CoversClass(TransactionManager::class)]
#[CoversClass(VersionLock::class)]
#[CoversClass(WriteJournal::class)]
#[CoversClass(JournalEntry::class)]
#[CoversClass(PersistenceException::class)]
#[CoversClass(StaleEntityException::class)]
#[Group('integration')]
final class TransactionalTest extends TestCase
{
    use TestDatabaseTrait;

    private EntityManager $em;

    #[Test]
    public function commitsWorkAndReturnsResult(): void
    {
        $result = $this->em->transactional(function (): string {
            $this->em->save(EntityFactory::user());

            return 'done';
        });

        static::assertSame(['done', 1], [$result, count($this->fetchAll('SELECT * FROM users'))]);
    }

    #[Test]
    public function entityInsertedInRolledBackTransactionCanBeSavedAgain(): void
    {
        $user = EntityFactory::user();
        $this->failingWith(fn() => $this->em->save($user));

        $this->em->save($user);

        static::assertSame(1, count($this->fetchAll('SELECT * FROM users')));
    }

    #[Test]
    public function nestedCallsJoinOuterTransaction(): void
    {
        $this->failingWith(function (): void {
            $this->em->transactional(fn() => $this->em->save(EntityFactory::user()));
        });

        static::assertSame([], $this->fetchAll('SELECT * FROM users'));
    }

    #[Test]
    public function rollbackKeepsDeletedEntityManaged(): void
    {
        $user = EntityFactory::user();
        $this->em->save($user);

        $this->failingWith(fn() => $this->em->delete($user));

        static::assertSame([true, 1], [$this->em->contains($user), count($this->fetchAll('SELECT * FROM users'))]);
    }

    #[Test]
    public function rollbackOfEntityWithoutGeneratedKeyOrVersionLeavesItUnmanaged(): void
    {
        $membership = EntityFactory::membership();

        $this->failingWith(fn() => $this->em->save($membership));

        static::assertSame([false, []], [
            $this->em->contains($membership),
            $this->fetchAll('SELECT * FROM crm.memberships'),
        ]);
    }

    #[Test]
    public function rollbackOfRefreshRestoresVersionHeldBeforeIt(): void
    {
        $widget = EntityFactory::widget();
        $this->em->save($widget);
        $this->pdo->exec('UPDATE widgets SET version = 5');

        $this->failingWith(fn() => $this->em->refresh($widget));

        static::assertSame(1, $widget->version);
    }

    #[Test]
    public function rollbackReplaysRepeatedWritesNewestFirst(): void
    {
        $widget = EntityFactory::widget('one');
        $this->em->save($widget);

        $this->failingWith(function () use ($widget): void {
            $widget->name = 'two';
            $this->em->save($widget);
            $widget->name = 'three';
            $this->em->save($widget);
        });

        static::assertSame(
            [1, [['name' => 'one', 'version' => 1]]],
            [$widget->version, $this->fetchAll('SELECT name, version FROM widgets')],
        );
    }

    #[Test]
    public function rollbackRestoresSnapshotAndVersionOfUpdatedEntity(): void
    {
        $widget = EntityFactory::widget('before');
        $this->em->save($widget);
        $widget->name = 'after';

        $this->failingWith(fn() => $this->em->save($widget));
        $this->em->save($widget);

        static::assertSame(
            [2, [['name' => 'after', 'version' => 2]]],
            [$widget->version, $this->fetchAll('SELECT name, version FROM widgets')],
        );
    }

    #[Test]
    public function rollbackUndoesRowsAndMakesInsertedEntitiesNewAgain(): void
    {
        $user   = EntityFactory::user();
        $widget = EntityFactory::widget();

        $this->failingWith(function () use ($user, $widget): void {
            $this->em->save($user);
            $this->em->save($widget);
        });

        static::assertSame(
            [[], null, false, false],
            [
                $this->fetchAll('SELECT * FROM users'),
                $user->id,
                (new ReflectionProperty($widget, 'id'))->isInitialized($widget),
                $this->em->contains($user),
            ],
        );
    }

    #[Test]
    public function saveAndRefreshRollsBackWhenRefreshFails(): void
    {
        $trigger = Schema::vanishingUserTrigger($this->platform);
        if (null === $trigger) {
            static::markTestSkipped("{$this->platform->value} triggers cannot delete from the table that fired them");
        }

        $this->execAll($trigger);
        $user = EntityFactory::user();

        try {
            $this->em->saveAndRefresh($user);
            static::fail('Expected the refresh to fail');
        } catch (PersistenceException) {
            static::assertSame([null, false], [$user->id, $this->em->contains($user)]);
        }
    }

    protected function setUp(): void
    {
        $this->setUpTestDatabase();
        $this->em = new EntityManager($this->adapter);
    }

    private function failingWith(callable $work): void
    {
        try {
            $this->em->transactional(static function () use ($work): void {
                $work();

                throw new RuntimeException('abort');
            });
        } catch (RuntimeException $e) {
            static::assertSame('abort', $e->getMessage());
        }
    }
}
