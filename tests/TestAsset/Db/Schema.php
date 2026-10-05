<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Db;

/**
 * SQLite DDL for every fixture entity in TestAsset\Entity.
 */
final class Schema
{
    public const array ALL = [
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
    ];
}
