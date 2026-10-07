<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Version;

/**
 * Natural identifier plus an optimistic-lock version: no generated key.
 */
#[Table('revisions')]
final class Revision
{
    #[Id]
    public int $id = 1;

    #[Version]
    public int $version = 1;
}
