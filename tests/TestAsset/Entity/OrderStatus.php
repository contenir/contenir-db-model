<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';
}
