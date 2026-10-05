<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Value\SensitiveString;

#[Table('accounts')]
final class Account
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Column('password_hash')]
    public SensitiveString $passwordHash;

    #[Column('api_token', sensitive: true)]
    public ?string $apiToken = null;

    #[Column]
    public string $username;
}
