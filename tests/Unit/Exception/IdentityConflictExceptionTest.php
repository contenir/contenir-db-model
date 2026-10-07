<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Exception;

use Contenir\Db\Model\Exception\IdentityConflictException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentityConflictException::class)]
#[Group('unit')]
final class IdentityConflictExceptionTest extends TestCase
{
    #[Test]
    public function alreadyManagedPreservesZeroFractionAndSubstitutesInvalidUtf8(): void
    {
        $exception = IdentityConflictException::alreadyManaged('Entity', ['id' => 1.0, 'tag' => "a\xB1b"]);

        static::assertSame(
            'Another instance of "Entity" with identifier {"id":1.0,"tag":"a\\ufffdb"} is already managed; '
                . 'load it instead of creating a new one',
            $exception->getMessage(),
        );
    }
}
