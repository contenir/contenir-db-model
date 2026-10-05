<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

#[Table('union_column')]
final class UnionTypeColumnEntity
{
    #[Id]
    public int $id;

    #[Column]
    public int|string $value;
}
