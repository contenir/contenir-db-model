<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

/**
 * Has its own `sequence` column, like the join table that links it to
 * albums, so ordering by the join table's column must be qualified.
 */
#[Table('photos')]
final class Photo
{
    #[Id]
    public int $id;

    #[Column]
    public string $caption;

    #[Column]
    public int $sequence;
}
