<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use Contenir\Db\Model\Repository\Factory\RepositoryFactory;
use Contenir\Db\Model\Repository\RepositoryLookup;
use ContenirTest\Db\Model\TestAsset\Fixture;
use ContenirTest\Db\Model\TestAsset\InMemoryContainer;
use ContenirTest\Db\Model\TestAsset\OrderEntity;
use ContenirTest\Db\Model\TestAsset\OrderRepository;
use ContenirTest\Db\Model\TestAsset\ProfileEntity;
use ContenirTest\Db\Model\TestAsset\ProfileRepository;
use ContenirTest\Db\Model\TestAsset\TagEntity;
use ContenirTest\Db\Model\TestAsset\TagRepository;
use ContenirTest\Db\Model\TestAsset\UserEntity;
use ContenirTest\Db\Model\TestAsset\UserRepository;
use ContenirTest\Db\Model\TestAsset\VersionedWidgetEntity;
use ContenirTest\Db\Model\TestAsset\VersionedWidgetRepository;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

abstract class AbstractIntegrationTestCase extends TestCase
{
    protected Adapter $adapter;
    protected InMemoryContainer $container;
    protected UserRepository $users;
    protected ProfileRepository $profiles;
    protected OrderRepository $orders;
    protected TagRepository $tags;
    protected VersionedWidgetRepository $widgets;

    protected function setUp(): void
    {
        $this->adapter   = Fixture::adapter();
        $this->container = new InMemoryContainer();

        $this->container->set('config', [
            'model' => [
                'adapter' => Adapter::class,
                'map'     => [
                    UserRepository::class            => UserEntity::class,
                    ProfileRepository::class         => ProfileEntity::class,
                    OrderRepository::class           => OrderEntity::class,
                    TagRepository::class             => TagEntity::class,
                    VersionedWidgetRepository::class => VersionedWidgetEntity::class,
                ],
            ],
        ]);

        $this->container->set(Adapter::class, $this->adapter);
        $this->container->set(UserEntity::class, new UserEntity());
        $this->container->set(ProfileEntity::class, new ProfileEntity());
        $this->container->set(OrderEntity::class, new OrderEntity());
        $this->container->set(TagEntity::class, new TagEntity());
        $this->container->set(VersionedWidgetEntity::class, new VersionedWidgetEntity());
        $this->container->set(RepositoryLookup::class, new RepositoryLookup($this->container));

        $factory = new RepositoryFactory();

        $this->users    = $factory($this->container, UserRepository::class);
        $this->profiles = $factory($this->container, ProfileRepository::class);
        $this->orders   = $factory($this->container, OrderRepository::class);
        $this->tags     = $factory($this->container, TagRepository::class);
        $this->widgets  = $factory($this->container, VersionedWidgetRepository::class);

        $this->container->set(UserRepository::class, $this->users);
        $this->container->set(ProfileRepository::class, $this->profiles);
        $this->container->set(OrderRepository::class, $this->orders);
        $this->container->set(TagRepository::class, $this->tags);
        $this->container->set(VersionedWidgetRepository::class, $this->widgets);
    }
}
