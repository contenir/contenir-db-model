<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Exception;

use Contenir\Db\Model\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ConfigurationException::class)]
#[Group('unit')]
final class ConfigurationExceptionTest extends TestCase
{
    #[Test]
    public function unbuildableRepositoryWrapsPreviousWithZeroCode(): void
    {
        $previous = new RuntimeException('boom');

        $exception = ConfigurationException::unbuildableRepository('Repo', $previous);

        static::assertSame(
            'Cannot build repository "Repo" with the EntityManager as its only argument: boom',
            $exception->getMessage(),
        );
        static::assertSame(0, $exception->getCode());
        static::assertSame($previous, $exception->getPrevious());
    }
}
