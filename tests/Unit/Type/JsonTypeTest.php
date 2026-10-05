<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Type\JsonType;
use ContenirTest\Db\Model\TestAsset\Factory\FieldFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const NAN;

#[CoversClass(JsonType::class)]
#[CoversClass(TypeConversionException::class)]
#[Group('unit')]
final class JsonTypeTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidJsonProvider(): array
    {
        return [
            'malformed text' => ['{"tags":'],
            'non-string'     => [42],
        ];
    }

    #[Test]
    public function decodesObjectsToAssociativeArrays(): void
    {
        $value = (new JsonType())->toPhp('{"tags":["a","b"],"count":2}', FieldFactory::make(null, typeName: 'json'));

        static::assertSame(['tags' => ['a', 'b'], 'count' => 2], $value);
    }

    #[Test]
    public function encodesWithoutEscapingSlashesOrUnicodeAndKeepsFloatFractions(): void
    {
        $value = (new JsonType())->toDatabase(
            ['url' => 'a/b', 'name' => 'Zoë', 'ratio' => 1.0],
            FieldFactory::make(null, typeName: 'json'),
        );

        static::assertSame('{"url":"a/b","name":"Zoë","ratio":1.0}', $value);
    }

    #[DataProvider('invalidJsonProvider')]
    #[Test]
    public function rejectsInvalidJson(mixed $value): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('to JSON text');

        (new JsonType())->toPhp($value, FieldFactory::make(null, typeName: 'json'));
    }

    #[Test]
    public function rejectsUnencodableValue(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('to JSON-encodable value');

        (new JsonType())->toDatabase(NAN, FieldFactory::make(null, typeName: 'json'));
    }
}
