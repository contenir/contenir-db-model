<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Version;

#[Table('multiple_version')]
final class MultipleVersionEntity
{
    #[Id]
    public int $id;

    #[Version]
    public int $version = 1;

    #[Version]
    public int $revision = 1;
}
