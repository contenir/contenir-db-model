<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

#[Table('tags')]
final class Tag
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Column]
    public string $name;

    #[Column]
    public bool $active = true;
}
