<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Exception;

use Contenir\Db\Model\Exception\ExceptionInterface;
use Contenir\Db\Model\Exception\InvalidArgumentException;
use Contenir\Db\Model\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testInvalidArgumentExceptionImplementsExceptionInterface(): void
    {
        $exception = new InvalidArgumentException('msg');

        $this->assertInstanceOf(ExceptionInterface::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testRuntimeExceptionImplementsExceptionInterface(): void
    {
        $exception = new RuntimeException('msg');

        $this->assertInstanceOf(ExceptionInterface::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
