<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Factory;

use Contenir\Db\Model\Value\SensitiveString;
use ContenirTest\Db\Model\TestAsset\Entity\Account;
use ContenirTest\Db\Model\TestAsset\Entity\Membership;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\TestAsset\Entity\Widget;
use DateTimeImmutable;

/**
 * Builds new (unsaved) fixture entities with valid defaults.
 */
final class EntityFactory
{
    public static function account(string $username = 'alice'): Account
    {
        $account               = new Account();
        $account->username     = $username;
        $account->passwordHash = new SensitiveString('$2y$10$hash');

        return $account;
    }

    public static function membership(int $groupId = 1, int $userId = 2, string $role = 'owner'): Membership
    {
        return new Membership($groupId, $userId, $role, ['since' => 2024]);
    }

    public static function tag(string $name = 'news'): Tag
    {
        $tag       = new Tag();
        $tag->name = $name;

        return $tag;
    }

    public static function user(string $email = 'a@example.com', ?string $name = null): User
    {
        $user            = new User();
        $user->email     = $email;
        $user->name      = $name;
        $user->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');

        return $user;
    }

    public static function widget(string $name = 'sprocket'): Widget
    {
        $widget       = new Widget();
        $widget->name = $name;

        return $widget;
    }
}
