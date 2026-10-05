<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Type;

use BackedEnum;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Type\BackedEnumType;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use ContenirTest\Db\Model\TestAsset\Entity\Priority;
use ContenirTest\Db\Model\TestAsset\Factory\FieldFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackedEnumType::class)]
#[CoversClass(TypeConversionException::class)]
#[Group('unit')]
final class BackedEnumTypeTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidValueProvider(): array
    {
        return [
            'unknown backing value' => ['cancelled'],
            'unsupported type'      => [1.5],
        ];
    }

    /**
     * @return array<string, array{class-string<BackedEnum>, mixed, BackedEnum}>
     */
    public static function toPhpProvider(): array
    {
        return [
            'string-backed'          => [OrderStatus::class, 'shipped', OrderStatus::Shipped],
            'int-backed from int'    => [Priority::class, 2, Priority::High],
            'int-backed from string' => [Priority::class, '1', Priority::Low],
            'existing case'          => [Priority::class, Priority::High, Priority::High],
        ];
    }

    #[Test]
    public function rejectsCaseOfAnotherEnumForDatabase(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Cannot convert ' . OrderStatus::class);

        (new BackedEnumType())->toDatabase(OrderStatus::Shipped, FieldFactory::make(Priority::class));
    }

    #[Test]
    public function rejectsFieldNotDeclaredAsBackedEnum(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Cannot determine a type converter for property $value');

        (new BackedEnumType())->toPhp('x', FieldFactory::make('string', typeName: 'enum'));
    }

    #[DataProvider('invalidValueProvider')]
    #[Test]
    public function rejectsValueWithoutMatchingCase(mixed $value): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('to ' . OrderStatus::class);

        (new BackedEnumType())->toPhp($value, FieldFactory::make(OrderStatus::class));
    }

    /**
     * @param class-string<BackedEnum> $enum
     */
    #[DataProvider('toPhpProvider')]
    #[Test]
    public function resolvesCaseFromBackingValue(string $enum, mixed $value, BackedEnum $expected): void
    {
        static::assertSame($expected, (new BackedEnumType())->toPhp($value, FieldFactory::make($enum)));
    }

    #[Test]
    public function writesBackingValue(): void
    {
        static::assertSame(2, (new BackedEnumType())->toDatabase(Priority::High, FieldFactory::make(Priority::class)));
    }
}
