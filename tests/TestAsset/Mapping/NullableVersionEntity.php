<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Version;

#[Table('nullable_version')]
final class NullableVersionEntity
{
    #[Id]
    public int $id;

    #[Version]
    public ?int $version = null;
}
