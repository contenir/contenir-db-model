<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Metadata;

use Contenir\Db\Model\Metadata\FieldRole;
use Contenir\Db\Model\Metadata\RelationKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldRole::class)]
#[CoversClass(RelationKind::class)]
#[Group('unit')]
final class RoleAndKindTest extends TestCase
{
    /**
     * @return array<string, array{RelationKind, bool}>
     */
    public static function kindProvider(): array
    {
        return [
            'has one'      => [RelationKind::HasOne, false],
            'has many'     => [RelationKind::HasMany, true],
            'belongs to'   => [RelationKind::BelongsTo, false],
            'many to many' => [RelationKind::ManyToMany, true],
        ];
    }

    /**
     * @return array<string, array{FieldRole, bool, bool}>
     */
    public static function roleProvider(): array
    {
        return [
            'column'               => [FieldRole::Column, false, false],
            'identifier'           => [FieldRole::Identifier, true, false],
            'generated identifier' => [FieldRole::GeneratedIdentifier, true, true],
            'version'              => [FieldRole::Version, false, true],
        ];
    }

    #[DataProvider('roleProvider')]
    #[Test]
    public function fieldRoleReportsIdentifierAndWriteBack(FieldRole $role, bool $identifier, bool $writtenBack): void
    {
        static::assertSame([$identifier, $writtenBack], [$role->isIdentifier(), $role->isWrittenBack()]);
    }

    #[DataProvider('kindProvider')]
    #[Test]
    public function relationKindReportsWhetherItIsACollection(RelationKind $kind, bool $collection): void
    {
        static::assertSame($collection, $kind->isCollection());
    }
}
