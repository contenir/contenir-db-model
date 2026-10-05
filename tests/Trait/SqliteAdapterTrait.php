<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Trait;

use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sqlite\AdapterPlatform;
use PhpDb\Sqlite\Pdo\Connection;
use PhpDb\Sqlite\Pdo\Driver;
use PhpDb\Sqlite\Pdo\Feature\SqliteRowCounter;

/**
 * Builds a fresh in-memory SQLite adapter per test so no state survives
 * between tests. Call {@see self::setUpSqliteAdapter()} from setUp().
 */
trait SqliteAdapterTrait
{
    private AdapterInterface $adapter;

    private PDO $pdo;

    protected function setUpSqliteAdapter(string ...$schema): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach ($schema as $statement) {
            $this->pdo->exec($statement);
        }

        $driver = new Driver(
            connection: new Connection($this->pdo),
            features: [new SqliteRowCounter()],
        );

        $this->adapter = new Adapter($driver, new AdapterPlatform($driver));
    }
}
