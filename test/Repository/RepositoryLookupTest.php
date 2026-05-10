<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Repository;

use Contenir\Db\Model\Repository\RepositoryLookup;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class RepositoryLookupTest extends TestCase
{
    public function testGetContainerReturnsInjectedContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $lookup    = new RepositoryLookup($container);

        $this->assertSame($container, $lookup->getContainer());
    }
}
