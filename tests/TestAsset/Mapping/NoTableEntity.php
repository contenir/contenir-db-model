<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Id;

final class NoTableEntity
{
    #[Id]
    public int $id;
}
