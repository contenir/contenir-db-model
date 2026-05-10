<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Repository\Factory;

use Contenir\Db\Model\Repository\Factory\RepositoryFactory;
use Contenir\Db\Model\Repository\RepositoryLookup;
use ContenirTest\Db\Model\TestAsset\TestEntity;
use ContenirTest\Db\Model\TestAsset\TestRepository;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

class RepositoryFactoryTest extends TestCase
{
    public function testFactoryAssemblesRepositoryFromContainerServices(): void
    {
        $adapter = $this->getMockBuilder(Adapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $entity    = new TestEntity();
        $container = $this->createMock(ContainerInterface::class);
        $lookup    = new RepositoryLookup($container);

        $entityClass = TestEntity::class;
        $config      = [
            'model' => [
                'adapter' => Adapter::class,
                'map'     => [
                    TestRepository::class => $entityClass,
                ],
            ],
        ];

        $container->method('get')
            ->willReturnCallback(function ($id) use ($config, $adapter, $entity, $lookup) {
                return match ($id) {
                    'config'                => $config,
                    Adapter::class          => $adapter,
                    TestEntity::class       => $entity,
                    RepositoryLookup::class => $lookup,
                };
            });

        $factory    = new RepositoryFactory();
        $repository = $factory($container, TestRepository::class);

        $this->assertInstanceOf(TestRepository::class, $repository);
        $this->assertSame($adapter, $repository->getAdapter());
    }

    public function testGetEntityClassFallsBackToReplacingRepositoryWithEntity(): void
    {
        $factory = new RepositoryFactory();
        $method  = new ReflectionMethod($factory, 'getEntityClass');

        $config      = ['adapter' => Adapter::class];
        $requested   = 'App\\Model\\Repository\\UserRepository';
        $expected    = 'App\\Model\\Entity\\UserEntity';
        $entityClass = $method->invoke($factory, $config, $requested);

        $this->assertSame($expected, $entityClass);
    }

    public function testGetEntityClassUsesExplicitMapping(): void
    {
        $factory = new RepositoryFactory();
        $method  = new ReflectionMethod($factory, 'getEntityClass');

        $config = [
            'adapter' => Adapter::class,
            'map'     => [
                'App\\Model\\Repository\\WidgetRepository' => 'Custom\\WidgetEntity',
            ],
        ];

        $entityClass = $method->invoke(
            $factory,
            $config,
            'App\\Model\\Repository\\WidgetRepository'
        );

        $this->assertSame('Custom\\WidgetEntity', $entityClass);
    }

    public function testGetEntityClassDoesNotMangleUnrelatedRepositoryWord(): void
    {
        $factory = new RepositoryFactory();
        $method  = new ReflectionMethod($factory, 'getEntityClass');

        $config      = ['adapter' => Adapter::class];
        $requested   = 'App\\RepositoryRegistry\\Sub\\WidgetRepository';
        $expected    = 'App\\RepositoryRegistry\\Sub\\WidgetEntity';
        $entityClass = $method->invoke($factory, $config, $requested);

        $this->assertSame($expected, $entityClass);
    }

    public function testGetEntityClassReplacesNamespaceSegmentAndSuffixTogether(): void
    {
        $factory = new RepositoryFactory();
        $method  = new ReflectionMethod($factory, 'getEntityClass');

        $config      = ['adapter' => Adapter::class];
        $requested   = 'App\\Model\\Repository\\Subdir\\WidgetRepository';
        $expected    = 'App\\Model\\Entity\\Subdir\\WidgetEntity';
        $entityClass = $method->invoke($factory, $config, $requested);

        $this->assertSame($expected, $entityClass);
    }
}
