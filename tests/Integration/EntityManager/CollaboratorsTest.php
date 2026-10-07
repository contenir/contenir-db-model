<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\EntityManager;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Query\EntityReader;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Db\EmptyResultStatement;
use ContenirTest\Db\Model\TestAsset\Db\Platform;
use ContenirTest\Db\Model\TestAsset\Db\Schema;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\TestAsset\Factory\EntityFactory;
use ContenirTest\Db\Model\TestAsset\Metadata\RecordingMetadataFactory;
use ContenirTest\Db\Model\Trait\TestDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
#[CoversClass(EntityReader::class)]
#[CoversClass(TypeConversionException::class)]
#[Group('integration')]
final class CollaboratorsTest extends TestCase
{
    use TestDatabaseTrait;

    #[Test]
    public function countIsZeroWhenTheDriverReturnsNoRow(): void
    {
        $this->setUpSqliteAdapterWithStatement(new EmptyResultStatement(), ...Schema::create(Platform::Sqlite));

        static::assertSame(
            0,
            (new EntityManager($this->adapter))->getRepository(Order::class)
                ->count(),
        );
    }

    #[Test]
    public function customMetadataFactoryIsUsed(): void
    {
        $this->setUpTestDatabase();
        $metadata = new RecordingMetadataFactory();

        (new EntityManager($this->adapter, $metadata))->save(EntityFactory::user());

        static::assertSame([User::class], $metadata->requested);
    }

    #[Test]
    public function customTypeRegistryIsUsed(): void
    {
        $this->setUpTestDatabase("INSERT INTO users VALUES (1, 'a@example.com', 'Alice', '2024-01-01 00:00:00', 1)");

        $this->expectException(TypeConversionException::class);

        (new EntityManager($this->adapter, types: new TypeRegistry()))->getRepository(User::class)
            ->find(1);
    }
}
