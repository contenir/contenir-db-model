<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

#[Table('duplicate_column')]
final class DuplicateColumnEntity
{
    #[Id]
    public int $id;

    #[Column('label')]
    public string $name;

    #[Column('label')]
    public string $title;
}
