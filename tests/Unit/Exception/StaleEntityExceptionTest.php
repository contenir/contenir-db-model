<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Exception;

use Contenir\Db\Model\Exception\StaleEntityException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StaleEntityException::class)]
#[Group('unit')]
final class StaleEntityExceptionTest extends TestCase
{
    #[Test]
    public function forWritePreservesZeroFractionAndSubstitutesInvalidUtf8(): void
    {
        $exception = StaleEntityException::forWrite('update', 'Entity', ['id' => 1.0, 'tag' => "a\xB1b"], 3);

        static::assertSame(
            'Optimistic-lock failure on update of "Entity" {"id":1.0,"tag":"a\\ufffdb"}: '
                . 'no row with version 3 remains; reload and retry',
            $exception->getMessage(),
        );
    }
}
