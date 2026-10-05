<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Type\DateTimeType;
use ContenirTest\Db\Model\TestAsset\Factory\FieldFactory;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

#[CoversClass(DateTimeType::class)]
#[CoversClass(TypeConversionException::class)]
#[Group('unit')]
final class DateTimeTypeTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidValueProvider(): array
    {
        return [
            'unparseable string' => ['not a date'],
            'integer'            => [1_700_000_000],
        ];
    }

    #[Test]
    public function acceptsDateTimeInstancesFromDriver(): void
    {
        $value = (new DateTimeType())->toPhp(
            new DateTime('2024-03-04 05:06:07'),
            FieldFactory::make(DateTimeImmutable::class),
        );

        static::assertEquals(new DateTimeImmutable('2024-03-04 05:06:07'), $value);
    }

    #[Test]
    public function convertsToConfiguredTimezoneBeforeFormatting(): void
    {
        $type  = new DateTimeType(timezone: new DateTimeZone('UTC'));
        $value = $type->toDatabase(
            new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('Australia/Sydney')),
            FieldFactory::make(DateTimeImmutable::class),
        );

        static::assertSame('2023-12-31 23:00:00', $value);
    }

    #[Test]
    public function dateFormatZeroesTheTimeOfDay(): void
    {
        $value = (new DateTimeType('Y-m-d'))->toPhp('2024-03-04', FieldFactory::make(DateTimeImmutable::class));

        static::assertSame('2024-03-04 00:00:00', $value->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function fallsBackToGeneralParserForOtherFormats(): void
    {
        $value = (new DateTimeType())->toPhp(
            '2024-03-04 05:06:07.250000',
            FieldFactory::make(DateTimeImmutable::class),
        );

        static::assertSame('2024-03-04 05:06:07.250000', $value->format('Y-m-d H:i:s.u'));
    }

    #[Test]
    public function formatsForDatabase(): void
    {
        $value = (new DateTimeType())->toDatabase(
            new DateTime('2024-03-04 05:06:07'),
            FieldFactory::make(DateTime::class),
        );

        static::assertSame('2024-03-04 05:06:07', $value);
    }

    #[Test]
    public function parsesConfiguredFormatIntoImmutable(): void
    {
        $value = (new DateTimeType())->toPhp('2024-03-04 05:06:07', FieldFactory::make(DateTimeImmutable::class));

        static::assertEquals(new DateTimeImmutable('2024-03-04 05:06:07'), $value);
    }

    #[Test]
    public function parsesInConfiguredTimezone(): void
    {
        $type  = new DateTimeType(timezone: new DateTimeZone('Australia/Sydney'));
        $value = $type->toPhp('2024-01-01 10:00:00', FieldFactory::make(DateTimeImmutable::class));

        static::assertSame('2024-01-01T10:00:00+11:00', $value->format(DATE_ATOM));
    }

    #[Test]
    public function rejectsNonDateForDatabase(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage("string '2024-01-01'");

        (new DateTimeType())->toDatabase('2024-01-01', FieldFactory::make(DateTimeImmutable::class));
    }

    #[DataProvider('invalidValueProvider')]
    #[Test]
    public function rejectsUnparseableDatabaseValue(mixed $value): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('to DateTimeInterface');

        (new DateTimeType())->toPhp($value, FieldFactory::make(DateTimeImmutable::class));
    }

    #[Test]
    public function returnsMutableDateTimeForMutableProperties(): void
    {
        $value = (new DateTimeType())->toPhp('2024-03-04 05:06:07', FieldFactory::make(DateTime::class));

        static::assertEquals(new DateTime('2024-03-04 05:06:07'), $value);
    }
}
