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
use ContenirTest\Db\Model\Trait\SqliteAdapterTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
final class DeleteAndRefreshTest extends TestCase
{
    use SqliteAdapterTrait;

    private EntityManager $em;

    #[Test]
    public function clearStopsManagingEntities(): void
    {
        $user = EntityFactory::user();
        $this->em->save($user);

        $this->em->clear();

        static::assertFalse($this->em->contains($user));
    }

    #[Test]
    public function deleteOfConcurrentlyModifiedVersionedRowIsStale(): void
    {
        $widget = EntityFactory::widget();
        $this->em->save($widget);
        $this->pdo->exec('UPDATE widgets SET version = 9');

        $this->expectException(StaleEntityException::class);
        $this->expectExceptionMessage('Optimistic-lock failure on delete');

        $this->em->delete($widget);
    }

    #[Test]
    public function deleteOfUnmanagedEntityUsesItsKey(): void
    {
        $this->pdo->exec("INSERT INTO tags (id, name, active) VALUES (7, 'x', 1)");
        $tag     = EntityFactory::tag('x');
        $tag->id = 7;

        $this->em->delete($tag);

        static::assertSame([], $this->fetchAll('SELECT * FROM tags'));
    }

    #[Test]
    public function deleteOfUnsavedEntityIsRejected(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Cannot delete');

        $this->em->delete(EntityFactory::user());
    }

    #[Test]
    public function deleteRemovesRowAndStopsManagingEntity(): void
    {
        $user = EntityFactory::user();
        $this->em->save($user);

        $this->em->delete($user);

        static::assertSame([[], false], [$this->fetchAll('SELECT * FROM users'), $this->em->contains($user)]);
    }

    #[Test]
    public function refreshDiscardsUnsavedEditsAndLoadsStoredValues(): void
    {
        $user = EntityFactory::user('a@example.com', 'Alice');
        $this->em->save($user);
        $this->pdo->exec("UPDATE users SET name = 'Stored'");
        $user->email = 'edited@example.com';

        $this->em->refresh($user);
        $this->em->save($user);

        static::assertSame(
            ['a@example.com', 'Stored', [['email' => 'a@example.com', 'name' => 'Stored', 'version' => 1]]],
            [$user->email, $user->name, $this->fetchAll('SELECT email, name, version FROM users')],
        );
    }

    #[Test]
    public function refreshOfDeletedRowFails(): void
    {
        $user = EntityFactory::user();
        $this->em->save($user);
        $this->pdo->exec('DELETE FROM users');

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('No row for');

        $this->em->refresh($user);
    }

    #[Test]
    public function refreshOfUnsavedEntityFails(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Cannot refresh');

        $this->em->refresh(EntityFactory::user());
    }

    protected function setUp(): void
    {
        $this->setUpSqliteAdapter(...Schema::ALL);
        $this->em = new EntityManager($this->adapter);
    }
}
