<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Entity;

use Contenir\Db\Model\Entity\AbstractEntity;
use Contenir\Db\Model\Entity\BaseEntity;
use Contenir\Db\Model\Entity\EntityInterface;
use Laminas\EventManager\EventManagerAwareInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function method_exists;
use function sprintf;

class EntityInterfaceTest extends TestCase
{
    public function testInterfaceExtendsEventManagerAware(): void
    {
        $this->assertTrue(
            (new ReflectionClass(EntityInterface::class))->implementsInterface(EventManagerAwareInterface::class)
        );
    }

    public function testAbstractEntitySatisfiesTheWiderContract(): void
    {
        $reflection = new ReflectionClass(EntityInterface::class);

        foreach ($reflection->getMethods() as $method) {
            $this->assertTrue(
                method_exists(AbstractEntity::class, $method->getName()),
                sprintf(
                    'AbstractEntity must implement EntityInterface::%s',
                    $method->getName()
                )
            );
        }
    }

    public function testBaseEntityIsAnEntityInterface(): void
    {
        $this->assertInstanceOf(EntityInterface::class, new BaseEntity());
    }
}
