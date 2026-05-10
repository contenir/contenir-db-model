<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration;

use ContenirTest\Db\Model\TestAsset\UserEntity;

class SmokeTest extends IntegrationTestCase
{
    public function testFindLoadsExistingUser(): void
    {
        /** @var UserEntity $user */
        $user = $this->users->findOne(['id' => 1]);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertSame('alice@example.com', $user->email);
        $this->assertSame('Alice', $user->name);
    }
}
