<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use RuntimeException;

class TransactionalTest extends AbstractIntegrationTestCase
{
    public function testCommitsOnSuccess(): void
    {
        $result = $this->users->transactional(function () {
            $this->users->insert(['email' => 'tx@example.com', 'name' => 'Tx']);
            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertNotNull($this->users->findOne(['email' => 'tx@example.com']));
    }

    public function testRollsBackOnException(): void
    {
        try {
            $this->users->transactional(function () {
                $this->users->insert(['email' => 'rb@example.com', 'name' => 'Rb']);
                throw new RuntimeException('boom');
            });
            $this->fail('expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull($this->users->findOne(['email' => 'rb@example.com']));
    }

    public function testNestedCallsJoinTheOuterTransaction(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();

        $this->users->transactional(function () use ($connection) {
            $this->assertTrue($connection->inTransaction());

            $this->users->transactional(function () use ($connection) {
                $this->assertTrue($connection->inTransaction());
                $this->users->insert(['email' => 'n@example.com', 'name' => 'N']);
            });

            // Inner call did not commit; we are still inside the outer
            // transaction.
            $this->assertTrue($connection->inTransaction());
        });

        $this->assertFalse($connection->inTransaction());
        $this->assertNotNull($this->users->findOne(['email' => 'n@example.com']));
    }

    public function testInnerExceptionRollsBackTheOuterTransaction(): void
    {
        try {
            $this->users->transactional(function () {
                $this->users->insert(['email' => 'outer@example.com', 'name' => 'O']);

                $this->users->transactional(function () {
                    $this->users->insert(['email' => 'inner@example.com', 'name' => 'I']);
                    throw new RuntimeException('inner failed');
                });
            });
            $this->fail('expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull($this->users->findOne(['email' => 'outer@example.com']));
        $this->assertNull($this->users->findOne(['email' => 'inner@example.com']));
    }
}
