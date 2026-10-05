<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

#[Table('readonly_generated')]
final class ReadonlyGeneratedIdEntity
{
    #[Id(generated: true)]
    public readonly int $id;
}
