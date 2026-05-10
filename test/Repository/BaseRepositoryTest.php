<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Repository;

use Contenir\Db\Model\Entity\BaseEntity;
use Contenir\Db\Model\Repository\BaseRepository;
use Contenir\Db\Model\Repository\RepositoryLookup;
use ContenirTest\Db\Model\TestAsset\ConcreteBaseRepository;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class BaseRepositoryTest extends TestCase
{
    public function testIsAbstract(): void
    {
        $this->assertTrue((new ReflectionClass(BaseRepository::class))->isAbstract());
    }

    public function testCreateReturnsBaseEntity(): void
    {
        $adapter = $this->getMockBuilder(Adapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository = new ConcreteBaseRepository(
            $adapter,
            new BaseEntity(),
            new RepositoryLookup($this->createMock(ContainerInterface::class))
        );

        $entity = $repository->create();

        $this->assertInstanceOf(BaseEntity::class, $entity);
    }
}
