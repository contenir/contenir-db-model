<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Metadata;

use Contenir\Db\Model\Metadata\ColumnMapping;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Metadata\FieldRole;
use Contenir\Db\Model\Metadata\FieldType;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnMapping::class)]
#[Group('unit')]
final class ColumnMappingTest extends TestCase
{
    #[Test]
    public function identifierColumnsSkipPlainFieldsAroundIdentifiers(): void
    {
        $type    = new FieldType('string', false);
        $mapping = new ColumnMapping(User::class, 'users', null, [
            'label'  => new FieldMetadata('label', 'label', $type),
            'region' => new FieldMetadata('region', 'region_code', $type, FieldRole::Identifier),
            'note'   => new FieldMetadata('note', 'note', $type),
            'id'     => new FieldMetadata('id', 'id', $type, FieldRole::Identifier),
        ]);

        static::assertSame(['region_code', 'id'], $mapping->identifierColumns());
    }
}
