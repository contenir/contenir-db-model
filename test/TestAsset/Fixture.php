<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Laminas\Db\Adapter\Adapter;

class Fixture
{
    public static function adapter(): Adapter
    {
        $adapter = new Adapter([
            'driver'   => 'Pdo_Sqlite',
            'database' => ':memory:',
        ]);

        $statements = [
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                name TEXT NOT NULL
            )',
            'CREATE TABLE profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                bio TEXT
            )',
            'CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                total INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                label TEXT NOT NULL
            )',
            'CREATE TABLE user_tag (
                user_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL
            )',
            'CREATE TABLE versioned_widgets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                version INTEGER NOT NULL DEFAULT 1
            )',
            "INSERT INTO versioned_widgets (id, name, version) VALUES
                (1, 'Widget', 1)",
            "INSERT INTO users (id, email, name) VALUES
                (1, 'alice@example.com', 'Alice'),
                (2, 'bob@example.com', 'Bob')",
            "INSERT INTO profiles (id, user_id, bio) VALUES
                (1, 1, 'Alice bio'),
                (2, 2, 'Bob bio')",
            "INSERT INTO orders (id, user_id, total, created_at) VALUES
                (1, 1, 100, '2024-01-01'),
                (2, 1, 250, '2024-02-01'),
                (3, 2, 75, '2024-01-15')",
            "INSERT INTO tags (id, label) VALUES
                (1, 'vip'),
                (2, 'beta'),
                (3, 'staff')",
            "INSERT INTO user_tag (user_id, tag_id) VALUES
                (1, 1),
                (1, 2),
                (2, 3)",
        ];

        foreach ($statements as $sql) {
            $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }

        return $adapter;
    }
}
