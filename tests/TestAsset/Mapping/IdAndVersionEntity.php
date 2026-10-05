<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Version;

#[Table('id_and_version')]
final class IdAndVersionEntity
{
    #[Id]
    #[Version]
    public int $id;
}
