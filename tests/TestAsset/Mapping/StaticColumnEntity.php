<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

#[Table('static_column')]
final class StaticColumnEntity
{
    #[Column]
    public static string $shared = '';

    #[Id]
    public int $id;
}
