<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Value;

use Contenir\Db\Model\Value\SensitiveString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function ob_get_clean;
use function ob_start;
use function print_r;
use function str_contains;
use function var_dump;

#[CoversClass(SensitiveString::class)]
#[Group('unit')]
final class SensitiveStringTest extends TestCase
{
    #[Test]
    public function equalsComparesWrappedValues(): void
    {
        $value = new SensitiveString('a');

        static::assertSame([true, false], [
            $value->equals(new SensitiveString('a')),
            $value->equals(new SensitiveString('b')),
        ]);
    }

    #[Test]
    public function printRShowsRedactionMarkerInsteadOfValue(): void
    {
        $output = print_r(new SensitiveString('s3cret-hash'), return: true);

        static::assertSame([false, true], [str_contains($output, 's3cret-hash'), str_contains($output, '[redacted]')]);
    }

    #[Test]
    public function revealsWrappedValue(): void
    {
        static::assertSame('s3cret-hash', (new SensitiveString('s3cret-hash'))->reveal());
    }

    /**
     * @mago-expect lint:no-debug-symbols
     */
    #[Test]
    public function varDumpShowsRedactionMarkerInsteadOfValue(): void
    {
        ob_start();
        var_dump(new SensitiveString('s3cret-hash'));
        $output = (string) ob_get_clean();

        static::assertSame([false, true], [str_contains($output, 's3cret-hash'), str_contains($output, '[redacted]')]);
    }
}
