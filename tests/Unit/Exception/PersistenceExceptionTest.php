<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Exception;

use Contenir\Db\Model\Exception\PersistenceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersistenceException::class)]
#[Group('unit')]
final class PersistenceExceptionTest extends TestCase
{
    #[Test]
    public function rowNotFoundPreservesZeroFractionAndSubstitutesInvalidUtf8(): void
    {
        $exception = PersistenceException::rowNotFound('Entity', ['id' => 1.0, 'tag' => "a\xB1b"]);

        static::assertSame(
            'No row for "Entity" {"id":1.0,"tag":"a\\ufffdb"} exists; it may have been deleted',
            $exception->getMessage(),
        );
    }
}
