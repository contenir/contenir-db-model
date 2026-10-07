<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Metadata\ColumnMapping;
use Contenir\Db\Model\Metadata\RelationContext;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelationContext::class)]
#[CoversClass(MappingException::class)]
#[Group('unit')]
final class RelationContextTest extends TestCase
{
    private RelationContext $context;

    /**
     * @return array<string, array{string}>
     */
    public static function invalidJoinColumnProvider(): array
    {
        return [
            'empty'                => [''],
            'leading digit'        => ['2nd'],
            'qualified'            => ['user_tag.sequence'],
            'direction appended'   => ['sequence DESC'],
            'trailing newline'     => ["sequence\n"],
            'statement terminator' => ['sequence;'],
            'quoted'               => ['`sequence`'],
            'non-ascii letter'     => ['séquence'],
            'hyphen'               => ['sort-order'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validJoinColumnProvider(): array
    {
        return [
            'lower case'           => ['sequence'],
            'leading underscore'   => ['_position'],
            'mixed case and digit' => ['Sort_Order2'],
            'single letter'        => ['z'],
        ];
    }

    #[DataProvider('validJoinColumnProvider')]
    #[Test]
    public function acceptsPlainJoinColumnNames(string $column): void
    {
        static::assertSame($column, $this->context->joinColumn('user_tag', $column));
    }

    #[Test]
    public function invalidJoinColumnMessageNamesRelationAndJoinTable(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(
            'Relation '
                . User::class
                . '::$tags orders by "user_tag.sequence" on join table "user_tag"; '
                . 'it must be a plain column name (letters, digits and underscores, not starting with a digit)',
        );

        $this->context->joinColumn('user_tag', 'user_tag.sequence');
    }

    #[DataProvider('invalidJoinColumnProvider')]
    #[Test]
    public function rejectsJoinColumnsThatAreNotPlainNames(string $column): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('it must be a plain column name');

        $this->context->joinColumn('user_tag', $column);
    }

    #[Test]
    public function rejectsOwnerColumnsThatAreNotMapped(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('references column "missing", which is not mapped on "' . User::class . '"');

        $this->context->assertOwnerColumns(['missing']);
    }

    protected function setUp(): void
    {
        $this->context = new RelationContext(
            new ColumnMapping(User::class, 'users', null, []),
            new ColumnMapping(Tag::class, 'tags', null, []),
            'tags',
        );
    }
}
