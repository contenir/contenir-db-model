<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Exception;

use Contenir\Db\Model\Exception\HydrationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HydrationException::class)]
#[Group('unit')]
final class HydrationExceptionTest extends TestCase
{
    #[Test]
    public function cannotInstantiateWrapsPreviousWithZeroCode(): void
    {
        $previous = new RuntimeException('boom');

        $exception = HydrationException::cannotInstantiate('Entity', $previous);

        static::assertSame('Cannot instantiate entity "Entity": boom', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function inaccessiblePropertyWrapsPreviousWithZeroCode(): void
    {
        $previous = new RuntimeException('boom');

        $exception = HydrationException::inaccessibleProperty('Entity', 'name', $previous);

        static::assertSame('Cannot access property Entity::$name: boom', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function missingIdentifierDescribesColumns(): void
    {
        static::assertSame(
            'Cannot load entity "Entity": the row lacks a non-null value for identifier column(s) [a, b]; '
                . 'include the primary key in the select',
            HydrationException::missingIdentifier('Entity', ['a', 'b'])->getMessage(),
        );
    }
}
