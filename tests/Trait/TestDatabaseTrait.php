<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Trait;

use ContenirTest\Db\Model\TestAsset\Db\Platform;
use ContenirTest\Db\Model\TestAsset\Db\Schema;
use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Adapter\Profiler\Profiler;
use PhpDb\Mysql;
use PhpDb\Pgsql;

/**
 * Builds the fixture schema on the platform named by DB_PLATFORM (SQLite
 * by default, or MySQL / PostgreSQL from the DB_* variables) and seeds it.
 * Every call starts from an empty schema. Call
 * {@see self::setUpTestDatabase()} from setUp().
 */
trait TestDatabaseTrait
{
    use SqliteAdapterTrait;

    protected Platform $platform;

    /**
     * @param list<string> $statements
     */
    protected function execAll(array $statements): void
    {
        foreach ($statements as $statement) {
            $this->pdo->exec($statement);
        }
    }

    protected function setUpTestDatabase(string ...$seed): void
    {
        $this->platform = Platform::fromEnvironment();
        if (Platform::Sqlite === $this->platform) {
            $this->setUpSqliteAdapter(...Schema::create($this->platform), ...$seed);

            return;
        }

        $this->pdo = new PDO(
            $this->platform->dsn(),
            Platform::env('DB_USER', 'root'),
            Platform::env('DB_PASSWORD', ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        foreach ([...Schema::create($this->platform), ...$seed, ...Schema::afterSeed($this->platform)] as $statement) {
            $this->pdo->exec($statement);
        }

        $this->profiler = new Profiler();
        if (Platform::Mysql === $this->platform) {
            $driver        = new Mysql\Pdo\Driver(new Mysql\Pdo\Connection($this->pdo));
            $this->adapter = new Adapter($driver, new Mysql\AdapterPlatform($driver), profiler: $this->profiler);

            return;
        }

        $driver        = new Pgsql\Pdo\Driver(new Pgsql\Pdo\Connection($this->pdo));
        $this->adapter = new Adapter($driver, new Pgsql\AdapterPlatform($driver), profiler: $this->profiler);
    }
}
