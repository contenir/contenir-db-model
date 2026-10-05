<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

use PhpDb\Adapter\AdapterInterface;
use Throwable;

/**
 * Runs callables inside a database transaction. Re-entrant: a call made
 * while the connection is already in a transaction joins it, and only the
 * outermost frame commits or rolls back. The outermost frame also drives
 * the {@see WriteJournal} so in-memory tracking is rolled back with the
 * database.
 *
 * @internal
 */
final readonly class TransactionManager
{
    public function __construct(
        private AdapterInterface $adapter,
        private WriteJournal $journal,
    ) {}

    /**
     * @template R
     *
     * @param callable(): R $work
     *
     * @return R
     *
     * @throws Throwable Whatever $work throws, after rolling back.
     */
    public function transactional(callable $work): mixed
    {
        $connection = $this->adapter->getDriver()->getConnection();
        if ($connection->inTransaction()) {
            return $work();
        }

        $connection->beginTransaction();
        $this->journal->begin();

        try {
            $result = $work();
            $connection->commit();
        } catch (Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollback();
            }

            $this->journal->rollback();

            throw $e;
        }

        $this->journal->commit();

        return $result;
    }
}
