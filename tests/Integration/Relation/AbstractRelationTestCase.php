<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Relation;

use Contenir\Db\Model\EntityManager;
use ContenirTest\Db\Model\TestAsset\Db\Schema;
use ContenirTest\Db\Model\Trait\SqliteAdapterTrait;
use PHPUnit\Framework\TestCase;

/**
 * Shared fixture data for relation tests: three users, their orders,
 * profiles, tags, a membership with permissions and tickets.
 */
abstract class AbstractRelationTestCase extends TestCase
{
    use SqliteAdapterTrait;

    protected EntityManager $em;

    /**
     * @param iterable<object> $entities
     *
     * @return list<int|string>
     */
    protected static function ids(iterable $entities): array
    {
        $ids = [];
        foreach ($entities as $entity) {
            /** @var object{id: int|string} $entity */
            $ids[] = $entity->id;
        }

        return $ids;
    }

    protected function setUp(): void
    {
        $this->setUpSqliteAdapter(...Schema::ALL, ...[
            "INSERT INTO users VALUES (1, 'a@example.com', 'Alice', '2024-01-01 00:00:00', 1)",
            "INSERT INTO users VALUES (2, 'b@example.com', 'Bob', '2024-01-02 00:00:00', 1)",
            "INSERT INTO users VALUES (3, 'c@example.com', 'Cara', '2024-01-03 00:00:00', 1)",
            "INSERT INTO orders VALUES (1, 1, 100, 'pending', '2024-02-01 00:00:00')",
            "INSERT INTO orders VALUES (2, 1, 300, 'shipped', '2024-02-03 00:00:00')",
            "INSERT INTO orders VALUES (3, 2, 200, 'shipped', '2024-02-02 00:00:00')",
            "INSERT INTO profiles VALUES (1, 1, 'Hello from Alice')",
            "INSERT INTO tags VALUES (1, 'zeta', 1)",
            "INSERT INTO tags VALUES (2, 'alpha', 1)",
            "INSERT INTO tags VALUES (3, 'hidden', 0)",
            'INSERT INTO user_tag VALUES (1, 1), (1, 2), (1, 3), (2, 2)',
            "INSERT INTO crm.memberships VALUES (1, 2, 'owner', '{}')",
            "INSERT INTO crm.permissions VALUES (1, 1, 2, 'write'), (2, 1, 2, 'read'), (3, 1, 3, 'other')",
            'INSERT INTO tickets VALUES (1, 1), (2, 99)',
        ]);
        $this->em = new EntityManager($this->adapter);
    }
}
