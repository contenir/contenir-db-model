<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Identity;

use Contenir\Db\Model\Hydrator\ChangeTracker;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
use Contenir\Db\Model\Identity\EntityLoader;
use Contenir\Db\Model\Identity\IdentifierResolver;
use Contenir\Db\Model\Identity\IdentityMap;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Relation\RelationInitializer;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\Trait\SqliteAdapterTrait;
use PhpDb\Sql\Sql;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group('integration')]
final class IdentityMapIntegrationTest extends TestCase
{
    use SqliteAdapterTrait;

    #[Test]
    public function overlappingQueriesShareEntityInstances(): void
    {
        $types    = TypeRegistry::withDefaults();
        $hydrator = new EntityHydrator($types);
        $loader   = new EntityLoader(
            $hydrator,
            new ChangeTracker($hydrator),
            new IdentityMap(),
            new IdentifierResolver($types),
            new RelationInitializer(new PropertyAccessor()),
        );
        $metadata = (new AttributeMetadataFactory())->getMetadataFor(Order::class);
        $sql      = new Sql($this->adapter, 'orders');

        $byUser = [];
        foreach ($sql->prepareStatementForSqlObject($sql->select()->where(['user_id' => 9]))->execute() as $row) {
            /** @var array<string, mixed> $row */
            $byUser[] = $loader->load($metadata, $row);
        }

        $row = $sql->prepareStatementForSqlObject($sql->select()->where(['id' => 2]))
            ->execute()
            ->current();
        /** @var array<string, mixed> $row */
        $single = $loader->load($metadata, $row);

        static::assertSame($byUser[1], $single);
    }

    protected function setUp(): void
    {
        $this->setUpSqliteAdapter(
            'CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER, total INTEGER, status TEXT, placed_at TEXT)',
            "INSERT INTO orders VALUES (1, 9, 100, 'pending', '2024-01-01 00:00:00')",
            "INSERT INTO orders VALUES (2, 9, 200, 'shipped', '2024-01-02 00:00:00')",
        );
    }
}
