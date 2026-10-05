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
use ContenirTest\Db\Model\TestAsset\Db\NoGeneratedValueStatement;
use ContenirTest\Db\Model\TestAsset\Db\NullResultStatement;
use ContenirTest\Db\Model\TestAsset\Db\Platform;
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
final class DriverFailureTest extends TestCase
{
    use SqliteAdapterTrait;

    #[Test]
    public function missingGeneratedValueIsReported(): void
    {
        $this->setUpSqliteAdapterWithStatement(new NoGeneratedValueStatement(), ...Schema::create(Platform::Sqlite));

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('no generated value for ContenirTest\Db\Model\TestAsset\Entity\User::$id');

        (new EntityManager($this->adapter))->save(EntityFactory::user());
    }

    #[Test]
    public function missingSelectResultIsReported(): void
    {
        $this->setUpSqliteAdapterWithStatement(new NullResultStatement(), ...Schema::create(Platform::Sqlite));
        $tag     = EntityFactory::tag();
        $tag->id = 1;

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('returned no result');

        (new EntityManager($this->adapter))->refresh($tag);
    }

    #[Test]
    public function missingStatementResultIsReported(): void
    {
        $this->setUpSqliteAdapterWithStatement(new NullResultStatement(), ...Schema::create(Platform::Sqlite));

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('returned no result');

        (new EntityManager($this->adapter))->save(EntityFactory::user());
    }
}
