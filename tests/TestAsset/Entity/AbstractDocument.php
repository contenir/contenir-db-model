<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Id;

/**
 * Mapped superclass: declares a readonly identifier inherited by entities.
 */
abstract class AbstractDocument
{
    #[Id]
    public readonly int $id;
}
