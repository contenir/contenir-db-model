<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

class SaveModeDetectionTest extends AbstractIntegrationTestCase
{
    /**
     * Pre-v2: id=0 was treated as "unsaved" by array_filter and re-INSERTed
     * on every save, corrupting any legacy schema with a sentinel zero PK.
     * v2: only an explicit null PK triggers INSERT.
     */
    public function testSaveOnRowWithZeroPrimaryKeyDoesNotReInsert(): void
    {
        $this->adapter->query(
            'INSERT INTO users (id, email, name) VALUES (0, "zero@example.com", "Zero")',
            $this->adapter::QUERY_MODE_EXECUTE
        );

        $entity       = $this->users->findOne(['id' => 0]);
        $entity->name = 'ZeroRenamed';

        $this->users->save($entity);

        $reloaded = $this->users->findOne(['id' => 0]);
        $this->assertSame('ZeroRenamed', $reloaded->name);

        $stmt = $this->adapter->query(
            'SELECT COUNT(*) AS c FROM users',
            $this->adapter::QUERY_MODE_EXECUTE
        )->current();
        $this->assertSame(3, (int) $stmt['c']);
    }
}
