<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Metadata\ColumnMappingReader;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\TestAsset\Mapping\DuplicateColumnEntity;
use ContenirTest\Db\Model\TestAsset\Mapping\UnmappedFirstEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;

#[CoversClass(ColumnMappingReader::class)]
#[CoversClass(MappingException::class)]
#[Group('unit')]
final class ColumnMappingReaderTest extends TestCase
{
    #[Test]
    public function detectsDuplicateColumnAfterEarlierFields(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('maps column "label" more than once');

        (new ColumnMappingReader())->read(DuplicateColumnEntity::class);
    }

    #[Test]
    public function readsMappedPropertiesThatFollowAnUnmappedOne(): void
    {
        $mapping = (new ColumnMappingReader())->read(UnmappedFirstEntity::class);

        static::assertSame(['id', 'label'], array_keys($mapping->fields));
    }

    #[Test]
    public function returnsSameMappingOnRepeatedReads(): void
    {
        $reader = new ColumnMappingReader();

        static::assertSame($reader->read(User::class), $reader->read(User::class));
    }
}
