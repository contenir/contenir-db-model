<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

#[Table('composite_generated')]
final class CompositeGeneratedIdEntity
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Id]
    public int $tenantId;
}
