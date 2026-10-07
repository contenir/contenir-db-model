<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Db;

/**
 * DDL for every fixture entity in TestAsset\Entity, per platform. Server
 * platforms drop and recreate everything, so each test starts from the same
 * empty schema that a fresh in-memory SQLite database provides.
 */
final class Schema
{
    /**
     * Tables with generated identifiers, whose PostgreSQL identity
     * sequences must be advanced past explicitly seeded ids.
     */
    private const array GENERATED = ['users', 'orders', 'profiles', 'tags', 'accounts', 'widgets'];

    private const array TABLES = [
        'users',
        'orders',
        'profiles',
        'tags',
        'user_tag',
        'crm.memberships',
        'crm.permissions',
        'tickets',
        'accounts',
        'notes',
        'widgets',
        'albums',
        'photos',
        'album_photo',
    ];

    /**
     * Statements to run after seeding explicit identifiers.
     *
     * @return list<string>
     */
    public static function afterSeed(Platform $platform): array
    {
        if (Platform::Pgsql !== $platform) {
            return [];
        }

        $statements = [];
        foreach (self::GENERATED as $table) {
            $statements[] =
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), "
                . "COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)";
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    public static function create(Platform $platform): array
    {
        return match ($platform) {
            Platform::Sqlite => self::sqlite(),
            Platform::Mysql => [
                ...self::drop($platform),
                'CREATE DATABASE IF NOT EXISTS crm',
                ...self::server($platform),
            ],
            Platform::Pgsql => [
                ...self::drop($platform),
                'CREATE SCHEMA IF NOT EXISTS crm',
                ...self::server($platform),
            ],
        };
    }

    /**
     * A trigger that lower-cases users.email on insert.
     *
     * @return list<string>
     */
    public static function lowercaseEmailTrigger(Platform $platform): array
    {
        return match ($platform) {
            Platform::Sqlite => [
                'CREATE TRIGGER users_lower_email AFTER INSERT ON users BEGIN '
                    . 'UPDATE users SET email = lower(email) WHERE id = NEW.id; END',
            ],
            Platform::Mysql => [
                'CREATE TRIGGER users_lower_email BEFORE INSERT ON users FOR EACH ROW SET NEW.email = LOWER(NEW.email)',
            ],
            Platform::Pgsql => [
                'CREATE OR REPLACE FUNCTION users_lower_email() RETURNS trigger AS $$ '
                    . 'BEGIN NEW.email := lower(NEW.email); RETURN NEW; END $$ LANGUAGE plpgsql',
                'CREATE TRIGGER users_lower_email BEFORE INSERT ON users FOR EACH ROW EXECUTE FUNCTION users_lower_email()',
            ],
        };
    }

    /**
     * A trigger that deletes each user row right after it is inserted, or
     * null where the platform cannot (MySQL triggers may not modify the
     * table that fired them).
     *
     * @return list<string>|null
     */
    public static function vanishingUserTrigger(Platform $platform): ?array
    {
        return match ($platform) {
            Platform::Sqlite => [
                'CREATE TRIGGER users_vanish AFTER INSERT ON users BEGIN DELETE FROM users WHERE id = NEW.id; END',
            ],
            Platform::Mysql  => null,
            Platform::Pgsql => [
                'CREATE OR REPLACE FUNCTION users_vanish() RETURNS trigger AS $$ '
                    . 'BEGIN DELETE FROM users WHERE id = NEW.id; RETURN NULL; END $$ LANGUAGE plpgsql',
                'CREATE TRIGGER users_vanish AFTER INSERT ON users FOR EACH ROW EXECUTE FUNCTION users_vanish()',
            ],
        };
    }

    /**
     * Albums link to photos through album_photo; both photos and the join
     * table carry a `sequence` column.
     *
     * @return list<string>
     */
    private static function albums(string $text): array
    {
        return [
            "CREATE TABLE albums (id INTEGER PRIMARY KEY, title {$text} NOT NULL)",
            "CREATE TABLE photos (id INTEGER PRIMARY KEY, caption {$text} NOT NULL, sequence INTEGER NOT NULL)",
            'CREATE TABLE album_photo (album_id INTEGER NOT NULL, photo_id INTEGER NOT NULL, sequence INTEGER NOT NULL, '
                . 'PRIMARY KEY (album_id, photo_id))',
        ];
    }

    /**
     * @return list<string>
     */
    private static function drop(Platform $platform): array
    {
        $statements = [];
        foreach (self::TABLES as $table) {
            $statements[] = Platform::Pgsql === $platform
                ? "DROP TABLE IF EXISTS {$table} CASCADE"
                : "DROP TABLE IF EXISTS {$table}";
        }

        return $statements;
    }

    /**
     * MySQL and PostgreSQL share the DDL apart from generated keys and
     * timestamp types.
     *
     * @return list<string>
     */
    private static function server(Platform $platform): array
    {
        $generated = Platform::Mysql === $platform
            ? 'INT AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY';
        $timestamp = Platform::Mysql === $platform ? 'DATETIME' : 'TIMESTAMP(0)';

        return [
            "CREATE TABLE users (id {$generated}, email VARCHAR(255) NOT NULL, name VARCHAR(255) NULL, "
                . "created_at {$timestamp} NOT NULL, version INTEGER NOT NULL)",
            "CREATE TABLE orders (id {$generated}, user_id INTEGER NOT NULL, total INTEGER NOT NULL, "
                . "status VARCHAR(20) NOT NULL, placed_at {$timestamp} NOT NULL)",
            "CREATE TABLE profiles (id {$generated}, user_id INTEGER NOT NULL, bio TEXT NOT NULL)",
            "CREATE TABLE tags (id {$generated}, name VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL)",
            'CREATE TABLE user_tag (user_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY (user_id, tag_id))',
            'CREATE TABLE crm.memberships (group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, role VARCHAR(50) NOT NULL, '
                . 'meta TEXT NOT NULL, PRIMARY KEY (group_id, user_id))',
            'CREATE TABLE crm.permissions (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, '
                . 'name VARCHAR(255) NOT NULL)',
            'CREATE TABLE tickets (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)',
            "CREATE TABLE accounts (id {$generated}, password_hash VARCHAR(255) NOT NULL, api_token VARCHAR(255) NULL, "
                . 'username VARCHAR(255) NOT NULL)',
            "CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT NOT NULL, created_at {$timestamp} NOT NULL)",
            "CREATE TABLE widgets (id {$generated}, name VARCHAR(255) NOT NULL, version INTEGER NOT NULL)",
            ...self::albums('VARCHAR(255)'),
        ];
    }

    /**
     * @return list<string>
     */
    private static function sqlite(): array
    {
        return [
            "ATTACH DATABASE ':memory:' AS crm",
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, name TEXT, '
                . 'created_at TEXT NOT NULL, version INTEGER NOT NULL)',
            'CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, total INTEGER NOT NULL, '
                . 'status TEXT NOT NULL, placed_at TEXT NOT NULL)',
            'CREATE TABLE profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, bio TEXT NOT NULL)',
            'CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL)',
            'CREATE TABLE user_tag (user_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY (user_id, tag_id))',
            'CREATE TABLE crm.memberships (group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, role TEXT NOT NULL, '
                . 'meta TEXT NOT NULL, PRIMARY KEY (group_id, user_id))',
            'CREATE TABLE crm.permissions (id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, '
                . 'name TEXT NOT NULL)',
            'CREATE TABLE tickets (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)',
            'CREATE TABLE accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, password_hash TEXT NOT NULL, api_token TEXT, '
                . 'username TEXT NOT NULL)',
            'CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT NOT NULL, created_at TEXT NOT NULL)',
            'CREATE TABLE widgets (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, version INTEGER NOT NULL)',
            ...self::albums('TEXT'),
        ];
    }
}
