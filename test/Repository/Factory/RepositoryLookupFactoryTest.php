<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Repository\Factory;

use Contenir\Db\Model\Repository\Factory\RepositoryLookupFactory;
use Contenir\Db\Model\Repository\RepositoryLookup;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class RepositoryLookupFactoryTest extends TestCase
{
    public function testFactoryCreatesRepositoryLookupWithContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $factory   = new RepositoryLookupFactory();

        /** @var RepositoryLookup $lookup */
        $lookup = $factory($container, RepositoryLookup::class);

        $this->assertInstanceOf(RepositoryLookup::class, $lookup);
        $this->assertSame($container, $lookup->getContainer());
    }
}
