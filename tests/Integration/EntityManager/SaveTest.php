<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\EntityManager;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\StaleEntityException;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
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
use ContenirTest\Db\Model\TestAsset\Entity\Note;
use ContenirTest\Db\Model\TestAsset\Factory\EntityFactory;
use ContenirTest\Db\Model\Trait\SqliteAdapterTrait;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

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
final class SaveTest extends TestCase
{
    use SqliteAdapterTrait;

    private EntityManager $em;

    #[Test]
    public function insertAssignsGeneratedIdentifierToUninitialisedProperty(): void
    {
        $widget = EntityFactory::widget();
        $this->em->save($widget);

        static::assertSame(1, $widget->id);
    }

    #[Test]
    public function insertKeepsExplicitValueForGeneratedIdentifier(): void
    {
        $tag     = EntityFactory::tag();
        $tag->id = 42;

        $this->em->save($tag);

        static::assertSame([['id' => 42]], $this->fetchAll('SELECT id FROM tags'));
    }

    #[Test]
    public function insertsCompositeNaturalKeyIntoSchemaQualifiedTable(): void
    {
        $this->em->save(EntityFactory::membership(1, 2));

        static::assertSame(
            [['group_id' => 1, 'user_id' => 2, 'role' => 'owner', 'meta' => '{"since":2024}']],
            $this->fetchAll('SELECT * FROM crm.memberships'),
        );
    }

    #[Test]
    public function insertWithoutNaturalKeyIsRejectedBeforeAnySql(): void
    {
        $note = (new PropertyAccessor())->instantiate(Note::class);
        (new PropertyAccessor())->set($note, 'body', 'text');
        $note->createdAt = new DateTimeImmutable('2024-01-01');

        try {
            $this->em->save($note);
            static::fail('Expected a PersistenceException');
        } catch (PersistenceException $e) {
            static::assertSame([true, []], [
                str_contains($e->getMessage(), 'Cannot insert'),
                $this->fetchAll('SELECT * FROM notes'),
            ]);
        }
    }

    #[Test]
    public function insertWritesRowAndAssignsGeneratedIdentifier(): void
    {
        $user = EntityFactory::user('a@example.com', 'Alice');
        $this->em->save($user);

        static::assertSame(
            [
                1,
                true,
                [[
                    'id'         => 1,
                    'email'      => 'a@example.com',
                    'name'       => 'Alice',
                    'created_at' => '2024-01-01 00:00:00',
                    'version'    => 1,
                ]],
            ],
            [$user->id, $this->em->contains($user), $this->fetchAll('SELECT * FROM users')],
        );
    }

    #[Test]
    public function saveAndRefreshPicksUpDatabaseSideChanges(): void
    {
        $this->pdo->exec(
            'CREATE TRIGGER users_lower_email AFTER INSERT ON users BEGIN '
                . 'UPDATE users SET email = lower(email) WHERE id = NEW.id; END',
        );
        $user = EntityFactory::user('MiXeD@Example.COM');

        $this->em->saveAndRefresh($user);

        static::assertSame('mixed@example.com', $user->email);
    }

    #[Test]
    public function savingUnchangedManagedEntityIssuesNoUpdate(): void
    {
        $user = EntityFactory::user();
        $this->em->save($user);
        $this->pdo->exec("UPDATE users SET name = 'changed elsewhere'");

        $this->em->save($user);

        static::assertSame(['changed elsewhere', 1], [
            $this->fetchAll('SELECT name FROM users')[0]['name'] ?? null,
            $user->version,
        ]);
    }

    #[Test]
    public function storesSensitiveValueAsPlainText(): void
    {
        $this->em->save(EntityFactory::account());

        static::assertSame(
            '$2y$10$hash',
            $this->fetchAll('SELECT password_hash FROM accounts')[0]['password_hash'] ?? null,
        );
    }

    #[Test]
    public function updateBumpsVersion(): void
    {
        $widget = EntityFactory::widget();
        $this->em->save($widget);

        $widget->name = 'gear';
        $this->em->save($widget);

        static::assertSame([2, [['version' => 2]]], [$widget->version, $this->fetchAll('SELECT version FROM widgets')]);
    }

    #[Test]
    public function updateCanChangePrimaryKey(): void
    {
        $tag = EntityFactory::tag();
        $this->em->save($tag);

        $tag->id = 10;
        $this->em->save($tag);

        static::assertSame([['id' => 10, 'name' => 'news']], $this->fetchAll('SELECT id, name FROM tags'));
    }

    #[Test]
    public function updateOfConcurrentlyModifiedRowIsStale(): void
    {
        $widget = EntityFactory::widget();
        $this->em->save($widget);
        $this->pdo->exec('UPDATE widgets SET version = 5');

        $widget->name = 'gear';

        $this->expectException(StaleEntityException::class);
        $this->expectExceptionMessage('Optimistic-lock failure on update');

        $this->em->save($widget);
    }

    #[Test]
    public function updateThatClearsPrimaryKeyIsRejectedBeforeAnySql(): void
    {
        $user = EntityFactory::user();
        $this->em->save($user);
        $user->id    = null;
        $user->email = 'b@example.com';

        try {
            $this->em->save($user);
            static::fail('Expected a PersistenceException');
        } catch (PersistenceException $e) {
            static::assertSame([true, [['id' => 1, 'email' => 'a@example.com']]], [
                str_contains($e->getMessage(), 'Cannot update'),
                $this->fetchAll('SELECT id, email FROM users'),
            ]);
        }
    }

    #[Test]
    public function updateWritesOnlyChangedColumns(): void
    {
        $user = EntityFactory::user('a@example.com', 'Alice');
        $this->em->save($user);
        $this->pdo->exec("UPDATE users SET name = 'changed elsewhere', version = version");

        $user->email = 'b@example.com';
        $this->em->save($user);

        static::assertSame(
            [['email' => 'b@example.com', 'name' => 'changed elsewhere']],
            $this->fetchAll('SELECT email, name FROM users'),
        );
    }

    protected function setUp(): void
    {
        $this->setUpSqliteAdapter(...Schema::ALL);
        $this->em = new EntityManager($this->adapter);
    }
}
