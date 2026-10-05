<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Container;

use Contenir\Db\Model\Container\EntityManagerFactory;
use Contenir\Db\Model\Container\RepositoryFactory;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ConfigurationException;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Contenir\Db\Model\Repository;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Container\InMemoryContainer;
use ContenirTest\Db\Model\TestAsset\Db\Schema;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\TestAsset\Factory\EntityFactory;
use ContenirTest\Db\Model\TestAsset\Repository\UserRepository;
use ContenirTest\Db\Model\Trait\SqliteAdapterTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(EntityManagerFactory::class)]
#[CoversClass(RepositoryFactory::class)]
#[CoversClass(ConfigurationException::class)]
#[Group('integration')]
final class ContainerWiringTest extends TestCase
{
    use SqliteAdapterTrait;

    #[Test]
    public function buildsCustomRepositoryWithSharedEntityManager(): void
    {
        $container  = $this->container();
        $repository = (new RepositoryFactory())($container, UserRepository::class);

        static::assertInstanceOf(UserRepository::class, $repository);
    }

    #[Test]
    public function builtEntityManagerWritesThroughConfiguredAdapter(): void
    {
        $em = $this->container()->get(EntityManager::class);
        static::assertInstanceOf(EntityManager::class, $em);

        $em->save(EntityFactory::user());

        static::assertCount(1, $this->fetchAll('SELECT * FROM users'));
    }

    #[Test]
    public function managerFromContainerLoadsEntities(): void
    {
        $em = $this->container()->get(EntityManager::class);
        static::assertInstanceOf(EntityManager::class, $em);
        $em->save(EntityFactory::user());
        $em->clear();

        static::assertSame('a@example.com', $em->getRepository(User::class)->find(1)?->email);
    }

    #[Test]
    public function rejectsNonRepositoryClass(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must be an instance of ' . Repository::class);

        (new RepositoryFactory())($this->container(), stdClass::class);
    }

    #[Test]
    public function rejectsRepositoryNeedingMoreThanTheEntityManager(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Cannot build repository "' . Repository::class . '"');

        (new RepositoryFactory())($this->container(), Repository::class);
    }

    protected function setUp(): void
    {
        $this->setUpSqliteAdapter(...Schema::ALL);
    }

    private function container(): InMemoryContainer
    {
        $em = (new EntityManagerFactory())(new InMemoryContainer([
            'config'                        => ['contenir_db_model' => ['adapter' => 'db']],
            'db'                            => $this->adapter,
            MetadataFactoryInterface::class => new AttributeMetadataFactory(),
            TypeRegistry::class             => TypeRegistry::withDefaults(),
        ]));

        return new InMemoryContainer([EntityManager::class => $em]);
    }
}
