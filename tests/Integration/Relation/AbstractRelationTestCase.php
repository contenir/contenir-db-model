<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Relation;

use Contenir\Db\Model\EntityManager;
use ContenirTest\Db\Model\Trait\TestDatabaseTrait;
use PHPUnit\Framework\TestCase;

/**
 * Shared fixture data for relation tests: three users, their orders,
 * profiles, tags, a membership with permissions and tickets, and albums
 * whose photos are ordered by the join table's `sequence`, which
 * disagrees with the photos' own `sequence`.
 */
abstract class AbstractRelationTestCase extends TestCase
{
    use TestDatabaseTrait;

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
        $this->setUpTestDatabase(
            "INSERT INTO users VALUES (1, 'a@example.com', 'Alice', '2024-01-01 00:00:00', 1)",
            "INSERT INTO users VALUES (2, 'b@example.com', 'Bob', '2024-01-02 00:00:00', 1)",
            "INSERT INTO users VALUES (3, 'c@example.com', 'Cara', '2024-01-03 00:00:00', 1)",
            "INSERT INTO orders VALUES (1, 1, 100, 'pending', '2024-02-01 00:00:00')",
            "INSERT INTO orders VALUES (2, 1, 300, 'shipped', '2024-02-03 00:00:00')",
            "INSERT INTO orders VALUES (3, 2, 200, 'shipped', '2024-02-02 00:00:00')",
            "INSERT INTO profiles VALUES (1, 1, 'Hello from Alice')",
            "INSERT INTO tags VALUES (1, 'zeta', TRUE)",
            "INSERT INTO tags VALUES (2, 'alpha', TRUE)",
            "INSERT INTO tags VALUES (3, 'hidden', FALSE)",
            'INSERT INTO user_tag VALUES (1, 1), (1, 2), (1, 3), (2, 2)',
            "INSERT INTO crm.memberships VALUES (1, 2, 'owner', '{}')",
            "INSERT INTO crm.permissions VALUES (1, 1, 2, 'write'), (2, 1, 2, 'read'), (3, 1, 3, 'other')",
            'INSERT INTO tickets VALUES (1, 1), (2, 99)',
            "INSERT INTO albums VALUES (1, 'Summer'), (2, 'Winter'), (3, 'Empty')",
            "INSERT INTO photos VALUES (1, 'alpha', 10), (2, 'gamma', 30), (3, 'beta', 20)",
            'INSERT INTO album_photo VALUES (1, 1, 1), (1, 2, 1), (1, 3, 2), (2, 1, 3), (2, 2, 1), (2, 3, 2)',
        );
        $this->em = new EntityManager($this->adapter);
    }
}
