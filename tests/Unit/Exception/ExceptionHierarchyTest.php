<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Exception;

use Contenir\Db\Model\Exception\ExceptionInterface;
use Contenir\Db\Model\Exception\InvalidArgumentException;
use Contenir\Db\Model\Exception\RuntimeException;
use Contenir\Db\Model\Exception\StaleEntityException;
use InvalidArgumentException as SplInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException as SplRuntimeException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(RuntimeException::class)]
#[CoversClass(StaleEntityException::class)]
#[Group('unit')]
final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function exceptionProvider(): array
    {
        return [
            'invalid argument' => [InvalidArgumentException::class, SplInvalidArgumentException::class],
            'runtime'          => [RuntimeException::class, SplRuntimeException::class],
            'stale entity'     => [StaleEntityException::class, RuntimeException::class],
        ];
    }

    /**
     * @param class-string $class
     * @param class-string $parent
     */
    #[Test]
    #[DataProvider('exceptionProvider')]
    public function exceptionExtendsExpectedParentAndPackageMarker(string $class, string $parent): void
    {
        $exception = new $class('message');

        static::assertInstanceOf($parent, $exception);
        static::assertInstanceOf(ExceptionInterface::class, $exception);
    }
}
