<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Type\IntegerType;
use Contenir\Db\Model\Type\SensitiveStringType;
use Contenir\Db\Model\Value\SensitiveString;
use ContenirTest\Db\Model\TestAsset\Factory\FieldFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SensitiveStringType::class)]
#[CoversClass(TypeConversionException::class)]
#[Group('unit')]
final class SensitiveStringTypeTest extends TestCase
{
    #[Test]
    public function conversionErrorsOnSensitiveFieldsOmitTheValue(): void
    {
        try {
            (new IntegerType())->toPhp('top-secret-token', FieldFactory::make('int', sensitive: true));
            static::fail('Expected a TypeConversionException');
        } catch (TypeConversionException $e) {
            static::assertSame(
                'Cannot convert [redacted] value for property $value (column "value_column") to int',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    public function rejectsNonStringDatabaseValue(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('to ' . SensitiveString::class);

        (new SensitiveStringType())->toPhp(123, FieldFactory::make(SensitiveString::class, sensitive: true));
    }

    #[Test]
    public function rejectsPlainStringForDatabase(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Cannot convert [redacted] value');

        (new SensitiveStringType())->toDatabase('plain', FieldFactory::make(SensitiveString::class, sensitive: true));
    }

    #[Test]
    public function unwrapsSensitiveStringForDatabase(): void
    {
        $value = (new SensitiveStringType())->toDatabase(
            new SensitiveString('hash'),
            FieldFactory::make(SensitiveString::class, sensitive: true),
        );

        static::assertSame('hash', $value);
    }

    #[Test]
    public function wrapsDatabaseStringInSensitiveString(): void
    {
        $value = (new SensitiveStringType())->toPhp('hash', FieldFactory::make(
            SensitiveString::class,
            sensitive: true,
        ));

        static::assertSame('hash', $value->reveal());
    }
}
