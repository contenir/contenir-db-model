<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

class InMemoryContainerNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
