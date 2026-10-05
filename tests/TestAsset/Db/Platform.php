<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Db;

use function getenv;
use function is_string;

/**
 * Database the integration suite runs against, chosen with the
 * DB_PLATFORM environment variable (default: in-memory SQLite).
 */
enum Platform: string
{
    case Sqlite = 'sqlite';
    case Mysql  = 'mysql';
    case Pgsql  = 'pgsql';

    public static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return is_string($value) && '' !== $value ? $value : $default;
    }

    public static function fromEnvironment(): self
    {
        return self::from(self::env('DB_PLATFORM', 'sqlite'));
    }

    public function dsn(): string
    {
        $host = self::env('DB_HOST', '127.0.0.1');
        $name = self::env('DB_NAME', 'contenir_test');

        return match ($this) {
            self::Sqlite => 'sqlite::memory:',
            self::Mysql => "mysql:host={$host};port="
                . self::env('DB_PORT', '3306')
                . ";dbname={$name};charset=utf8mb4",
            self::Pgsql => "pgsql:host={$host};port=" . self::env('DB_PORT', '5432') . ";dbname={$name}",
        };
    }
}
