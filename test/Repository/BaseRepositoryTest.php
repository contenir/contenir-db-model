<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Repository;

use Contenir\Db\Model\Entity\BaseEntity;
use Contenir\Db\Model\Repository\BaseRepository;
use Contenir\Db\Model\Repository\RepositoryLookup;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class BaseRepositoryTest extends TestCase
{
    public function testCreateReturnsBaseEntity(): void
    {
        $adapter = $this->getMockBuilder(Adapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository = new BaseRepository(
            $adapter,
            new BaseEntity(),
            new RepositoryLookup($this->createMock(ContainerInterface::class))
        );

        $entity = $repository->create();

        $this->assertInstanceOf(BaseEntity::class, $entity);
    }
}
