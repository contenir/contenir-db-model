<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Type\BackedEnumType;
use Contenir\Db\Model\Type\BooleanType;
use Contenir\Db\Model\Type\DateTimeType;
use Contenir\Db\Model\Type\FloatType;
use Contenir\Db\Model\Type\IntegerType;
use Contenir\Db\Model\Type\JsonType;
use Contenir\Db\Model\Type\SensitiveStringType;
use Contenir\Db\Model\Type\StringType;
use Contenir\Db\Model\Type\TypeConverterInterface;
use Contenir\Db\Model\Type\TypeRegistry;
use Contenir\Db\Model\Value\SensitiveString;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use ContenirTest\Db\Model\TestAsset\Factory\FieldFactory;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(TypeRegistry::class)]
#[CoversClass(TypeConversionException::class)]
#[Group('unit')]
final class TypeRegistryTest extends TestCase
{
    /**
     * @return array<string, array{FieldMetadata, class-string<TypeConverterInterface>}>
     */
    public static function defaultResolutionProvider(): array
    {
        return [
            'int'                    => [FieldFactory::make('int'), IntegerType::class],
            'float'                  => [FieldFactory::make('float'), FloatType::class],
            'string'                 => [FieldFactory::make('string'), StringType::class],
            'bool'                   => [FieldFactory::make('bool'), BooleanType::class],
            'backed enum'            => [FieldFactory::make(OrderStatus::class), BackedEnumType::class],
            'DateTimeImmutable'      => [FieldFactory::make(DateTimeImmutable::class), DateTimeType::class],
            'DateTime'               => [FieldFactory::make(DateTime::class), DateTimeType::class],
            'DateTimeInterface'      => [FieldFactory::make(DateTimeInterface::class), DateTimeType::class],
            'SensitiveString'        => [FieldFactory::make(SensitiveString::class), SensitiveStringType::class],
            'explicit name wins'     => [FieldFactory::make('string', typeName: 'json'), JsonType::class],
            'explicit name on union' => [FieldFactory::make(null, typeName: 'date'), DateTimeType::class],
        ];
    }

    /**
     * @return array<string, array{FieldMetadata, string}>
     */
    public static function unresolvableProvider(): array
    {
        return [
            'array without type name' => [FieldFactory::make('array'), 'No type converter is registered for "array"'],
            'unknown explicit name'   => [FieldFactory::make('string', typeName: 'money'), 'registered for "money"'],
            'union without type name' => [FieldFactory::make(null), 'Cannot determine a type converter'],
        ];
    }

    #[Test]
    public function convertsNullToNullForNullableField(): void
    {
        static::assertNull(TypeRegistry::withDefaults()->toPhp(FieldFactory::make('int', nullable: true), null));
    }

    #[Test]
    public function delegatesNonNullDatabaseConversion(): void
    {
        static::assertSame('shipped', TypeRegistry::withDefaults()->toDatabase(
            FieldFactory::make(OrderStatus::class),
            OrderStatus::Shipped,
        ));
    }

    #[Test]
    public function delegatesNonNullToPhpConversion(): void
    {
        static::assertSame(OrderStatus::Shipped, TypeRegistry::withDefaults()->toPhp(
            FieldFactory::make(OrderStatus::class),
            'shipped',
        ));
    }

    #[DataProvider('unresolvableProvider')]
    #[Test]
    public function rejectsFieldWithoutConverter(FieldMetadata $field, string $message): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage($message);

        TypeRegistry::withDefaults()->converterFor($field);
    }

    #[Test]
    public function rejectsNullForNonNullableField(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Column "value_column" is NULL but property $value is not nullable');

        TypeRegistry::withDefaults()->toPhp(FieldFactory::make('int'), null);
    }

    #[Test]
    public function resolvesConverterRegisteredForClass(): void
    {
        $converter = $this->createStub(TypeConverterInterface::class);
        $registry  = TypeRegistry::withDefaults()->withConverter(stdClass::class, $converter);

        static::assertSame($converter, $registry->converterFor(FieldFactory::make(stdClass::class)));
    }

    /**
     * @param class-string<TypeConverterInterface> $expected
     */
    #[DataProvider('defaultResolutionProvider')]
    #[Test]
    public function resolvesDefaultConverters(FieldMetadata $field, string $expected): void
    {
        static::assertInstanceOf($expected, TypeRegistry::withDefaults()->converterFor($field));
    }

    #[Test]
    public function withConverterReplacesWithoutMutatingOriginal(): void
    {
        $original  = TypeRegistry::withDefaults();
        $converter = $this->createStub(TypeConverterInterface::class);
        $replaced  = $original->withConverter('int', $converter);

        static::assertSame(
            [true, true],
            [
                $replaced->converterFor(FieldFactory::make('int')) === $converter,
                $original->converterFor(FieldFactory::make('int')) instanceof IntegerType,
            ],
        );
    }

    #[Test]
    public function writesNullAsNull(): void
    {
        static::assertNull(TypeRegistry::withDefaults()->toDatabase(FieldFactory::make('int', nullable: true), null));
    }
}
