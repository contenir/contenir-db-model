<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

#[Table('untyped_column')]
final class UntypedColumnEntity
{
    #[Id]
    public int $id;

    /**
     * @var mixed
     */
    #[Column]
    public $value;
}
