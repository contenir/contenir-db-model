<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

enum Priority: int
{
    case Low  = 1;
    case High = 2;
}
