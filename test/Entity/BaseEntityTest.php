<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Entity;

use Contenir\Db\Model\Entity\AbstractEntity;
use Contenir\Db\Model\Entity\BaseEntity;
use Contenir\Db\Model\Entity\EntityInterface;
use PHPUnit\Framework\TestCase;

class BaseEntityTest extends TestCase
{
    public function testIsAnAbstractEntity(): void
    {
        $entity = new BaseEntity();

        $this->assertInstanceOf(AbstractEntity::class, $entity);
        $this->assertInstanceOf(EntityInterface::class, $entity);
    }

    public function testHasNoColumnsByDefault(): void
    {
        $entity = new BaseEntity();

        $this->assertSame([], $entity->getArrayCopy());
        $this->assertSame([], $entity->getPrimaryKeys());
        $this->assertSame([], $entity->getRelations());
    }
}
