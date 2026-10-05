<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Type\BooleanType;
use Contenir\Db\Model\Type\FloatType;
use Contenir\Db\Model\Type\IntegerType;
use Contenir\Db\Model\Type\StringType;
use Contenir\Db\Model\Type\TypeConverterInterface;
use ContenirTest\Db\Model\TestAsset\Factory\FieldFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(IntegerType::class)]
#[CoversClass(FloatType::class)]
#[CoversClass(StringType::class)]
#[CoversClass(BooleanType::class)]
#[CoversClass(TypeConversionException::class)]
#[Group('unit')]
final class ScalarTypesTest extends TestCase
{
    /**
     * @return array<string, array{TypeConverterInterface, mixed, string}>
     */
    public static function invalidToDatabaseProvider(): array
    {
        return [
            'int from string'    => [new IntegerType(), '5', "string '5'"],
            'float from word'    => [new FloatType(), 'abc', "string 'abc'"],
            'string from int'    => [new StringType(), 5, 'int 5'],
            'bool from int'      => [new BooleanType(), 1, 'int 1'],
            'string from object' => [new StringType(), new stdClass(), 'Cannot convert stdClass for'],
        ];
    }

    /**
     * @return array<string, array{TypeConverterInterface, mixed}>
     */
    public static function invalidToPhpProvider(): array
    {
        return [
            'int from decimal string' => [new IntegerType(), '4.2'],
            'int from word'           => [new IntegerType(), 'abc'],
            'int from float'          => [new IntegerType(), 4.0],
            'int from bool'           => [new IntegerType(), true],
            'float from word'         => [new FloatType(), 'abc'],
            'float from bool'         => [new FloatType(), true],
            'string from bool'        => [new StringType(), true],
            'string from array'       => [new StringType(), []],
            'bool from two'           => [new BooleanType(), 2],
            'bool from yes'           => [new BooleanType(), 'yes'],
        ];
    }

    /**
     * @return array<string, array{TypeConverterInterface, mixed, mixed}>
     */
    public static function toDatabaseProvider(): array
    {
        return [
            'int'            => [new IntegerType(), 5, 5],
            'float'          => [new FloatType(), 1.25, 1.25],
            'float from int' => [new FloatType(), 3, 3.0],
            'string'         => [new StringType(), 'x', 'x'],
            'bool'           => [new BooleanType(), false, false],
        ];
    }

    /**
     * @return array<string, array{TypeConverterInterface, mixed, mixed}>
     */
    public static function toPhpProvider(): array
    {
        return [
            'int from int'           => [new IntegerType(), 42, 42],
            'int from string'        => [new IntegerType(), '-7', -7],
            'float from float'       => [new FloatType(), 1.5, 1.5],
            'float from int'         => [new FloatType(), 2, 2.0],
            'float from string'      => [new FloatType(), '2.25', 2.25],
            'string from string'     => [new StringType(), 'abc', 'abc'],
            'string from int'        => [new StringType(), 10, '10'],
            'string from float'      => [new StringType(), 1.5, '1.5'],
            'bool from true'         => [new BooleanType(), true, true],
            'bool from int one'      => [new BooleanType(), 1, true],
            'bool from string one'   => [new BooleanType(), '1', true],
            'bool from t'            => [new BooleanType(), 't', true],
            'bool from true string'  => [new BooleanType(), 'true', true],
            'bool from false'        => [new BooleanType(), false, false],
            'bool from int zero'     => [new BooleanType(), 0, false],
            'bool from string zero'  => [new BooleanType(), '0', false],
            'bool from f'            => [new BooleanType(), 'f', false],
            'bool from false string' => [new BooleanType(), 'false', false],
        ];
    }

    #[DataProvider('toPhpProvider')]
    #[Test]
    public function convertsDatabaseValueToPhp(TypeConverterInterface $type, mixed $value, mixed $expected): void
    {
        static::assertSame($expected, $type->toPhp($value, FieldFactory::make(null)));
    }

    #[DataProvider('toDatabaseProvider')]
    #[Test]
    public function convertsPhpValueForDatabase(TypeConverterInterface $type, mixed $value, mixed $expected): void
    {
        static::assertSame($expected, $type->toDatabase($value, FieldFactory::make(null)));
    }

    #[DataProvider('invalidToDatabaseProvider')]
    #[Test]
    public function rejectsPhpValueOfWrongType(TypeConverterInterface $type, mixed $value, string $message): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage($message);

        $type->toDatabase($value, FieldFactory::make(null));
    }

    #[DataProvider('invalidToPhpProvider')]
    #[Test]
    public function rejectsUnconvertibleDatabaseValue(TypeConverterInterface $type, mixed $value): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('for property $value (column "value_column")');

        $type->toPhp($value, FieldFactory::make(null));
    }
}
