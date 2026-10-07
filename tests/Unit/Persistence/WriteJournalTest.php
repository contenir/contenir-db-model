<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Persistence;

use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Persistence\JournalEntry;
use Contenir\Db\Model\Persistence\Session;
use Contenir\Db\Model\Persistence\WriteJournal;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Revision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WriteJournal::class)]
#[CoversClass(JournalEntry::class)]
#[Group('unit')]
final class WriteJournalTest extends TestCase
{
    #[Test]
    public function recordOutsideTransactionIsIgnored(): void
    {
        $session  = Session::create(TypeRegistry::withDefaults());
        $metadata = (new AttributeMetadataFactory())->getMetadataFor(Revision::class);
        $revision = new Revision();
        $journal  = new WriteJournal($session);

        $journal->record($metadata, $revision);
        $revision->version = 7;
        $journal->rollback();

        static::assertSame(7, $revision->version);
    }

    #[Test]
    public function rollbackReplaysEntriesNewestFirst(): void
    {
        $session  = Session::create(TypeRegistry::withDefaults());
        $metadata = (new AttributeMetadataFactory())->getMetadataFor(Revision::class);
        $revision = new Revision();
        $journal  = new WriteJournal($session);
        $journal->begin();
        $journal->record($metadata, $revision);
        $revision->version = 2;
        $journal->record($metadata, $revision);
        $revision->version = 3;

        $journal->rollback();

        static::assertSame(1, $revision->version);
    }

    #[Test]
    public function rollbackRestoresVersionOfEntityWithoutGeneratedIdentifier(): void
    {
        $session  = Session::create(TypeRegistry::withDefaults());
        $metadata = (new AttributeMetadataFactory())->getMetadataFor(Revision::class);
        $revision = new Revision();
        $journal  = new WriteJournal($session);
        $journal->begin();
        $journal->record($metadata, $revision);
        $revision->version = 7;

        $journal->rollback();

        static::assertSame(1, $revision->version);
    }
}
