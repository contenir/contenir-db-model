<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Version;

/**
 * Generated identifier that starts uninitialised (non-nullable int with no
 * default) and an optimistic-lock version.
 */
#[Table('widgets')]
final class Widget
{
    #[Id(generated: true)]
    public int $id;

    #[Column]
    public string $name;

    #[Version]
    public int $version = 1;
}
