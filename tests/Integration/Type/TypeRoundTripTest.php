<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Type;

use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use ContenirTest\Db\Model\TestAsset\Entity\Priority;
use ContenirTest\Db\Model\TestAsset\Factory\FieldFactory;
use ContenirTest\Db\Model\Trait\SqliteAdapterTrait;
use DateTimeImmutable;
use PhpDb\Sql\Sql;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group('integration')]
final class TypeRoundTripTest extends TestCase
{
    use SqliteAdapterTrait;

    /**
     * @return array<string, array{FieldMetadata, mixed}>
     */
    public static function valueProvider(): array
    {
        return [
            'int'           => [FieldFactory::make('int'), -42],
            'float'         => [FieldFactory::make('float'), 3.5],
            'string'        => [FieldFactory::make('string'), 'Zoë'],
            'bool true'     => [FieldFactory::make('bool'), true],
            'bool false'    => [FieldFactory::make('bool'), false],
            'datetime'      => [
                FieldFactory::make(DateTimeImmutable::class),
                new DateTimeImmutable('2024-03-04 05:06:07'),
            ],
            'date'          => [
                FieldFactory::make(DateTimeImmutable::class, typeName: 'date'),
                new DateTimeImmutable('2024-03-04'),
            ],
            'json'          => [FieldFactory::make('array', typeName: 'json'), ['a' => [1, 2], 'b' => null]],
            'string enum'   => [FieldFactory::make(OrderStatus::class), OrderStatus::Shipped],
            'int enum'      => [FieldFactory::make(Priority::class), Priority::High],
            'nullable null' => [FieldFactory::make('int', nullable: true), null],
        ];
    }

    #[DataProvider('valueProvider')]
    #[Test]
    public function valueSurvivesWriteAndReadThroughSqlite(FieldMetadata $field, mixed $value): void
    {
        $types = TypeRegistry::withDefaults();
        $sql   = new Sql($this->adapter, 'value_store');

        $insert = $sql->insert()->values(['id' => 1, 'value_column' => $types->toDatabase($field, $value)]);
        $sql->prepareStatementForSqlObject($insert)->execute();

        $row = $sql->prepareStatementForSqlObject($sql->select()->where(['id' => 1]))
            ->execute()
            ->current();

        static::assertEquals($value, $types->toPhp($field, $row['value_column']));
    }

    protected function setUp(): void
    {
        $this->setUpSqliteAdapter('CREATE TABLE value_store (id INTEGER PRIMARY KEY, value_column)');
    }
}
