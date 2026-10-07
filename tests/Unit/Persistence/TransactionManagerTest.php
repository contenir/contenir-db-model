<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Persistence;

use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Persistence\Session;
use Contenir\Db\Model\Persistence\TransactionManager;
use Contenir\Db\Model\Persistence\WriteJournal;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Revision;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver\ConnectionInterface;
use PhpDb\Adapter\Driver\DriverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TransactionManager::class)]
#[Group('unit')]
final class TransactionManagerTest extends TestCase
{
    #[Test]
    public function beginsAndCommitsOutermostTransaction(): void
    {
        $connection = $this->connection([false]);
        $connection->expects(static::once())->method('beginTransaction');
        $connection->expects(static::once())->method('commit');

        static::assertSame('ok', $this->managerFor($connection)->transactional(static fn(): string => 'ok'));
    }

    #[Test]
    public function commitDiscardsJournaledWritesSoLaterRollbackIsHarmless(): void
    {
        $session  = Session::create(TypeRegistry::withDefaults());
        $journal  = new WriteJournal($session);
        $metadata = (new AttributeMetadataFactory())->getMetadataFor(Revision::class);
        $revision = new Revision();

        $this->managerFor($this->connection([false]), $journal)->transactional(
            static fn() => $journal->record($metadata, $revision),
        );
        $revision->version = 7;
        $journal->rollback();

        static::assertSame(7, $revision->version);
    }

    #[Test]
    public function joinsTransactionAlreadyOpenOnConnection(): void
    {
        $connection = $this->connection([true]);
        $connection->expects(static::never())->method('beginTransaction');
        $connection->expects(static::never())->method('commit');

        static::assertSame('joined', $this->managerFor($connection)->transactional(static fn(): string => 'joined'));
    }

    #[Test]
    public function rollsBackAndRethrowsWhenWorkFails(): void
    {
        $connection = $this->connection([false, true]);
        $connection->expects(static::once())->method('rollback');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->managerFor($connection)->transactional(static fn() => throw new RuntimeException('boom'));
    }

    #[Test]
    public function skipsRollbackWhenDriverAlreadyEndedTheTransaction(): void
    {
        $connection = $this->connection([false, false]);
        $connection->expects(static::never())->method('rollback');

        $this->expectException(RuntimeException::class);

        $this->managerFor($connection)->transactional(static fn() => throw new RuntimeException('deadlock'));
    }

    /**
     * @param list<bool> $inTransaction
     */
    private function connection(array $inTransaction): ConnectionInterface&MockObject
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('inTransaction')->willReturnOnConsecutiveCalls(...$inTransaction);

        return $connection;
    }

    /**
     * @param list<bool> $inTransaction successive answers to inTransaction()
     */
    private function managerFor(ConnectionInterface $connection, ?WriteJournal $journal = null): TransactionManager
    {
        $driver = $this->createStub(DriverInterface::class);
        $driver->method('getConnection')->willReturn($connection);
        $adapter = $this->createStub(AdapterInterface::class);
        $adapter->method('getDriver')->willReturn($driver);

        return new TransactionManager(
            $adapter,
            $journal ?? new WriteJournal(Session::create(TypeRegistry::withDefaults())),
        );
    }
}
