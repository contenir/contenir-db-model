<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Db;

use Override;
use PhpDb\Adapter\Driver\Pdo\Statement;
use PhpDb\Adapter\Driver\ResultInterface;
use PhpDb\Adapter\ParameterContainer;

/**
 * Statement prototype whose execution yields no result, as a misbehaving
 * driver might.
 */
final class NullResultStatement extends Statement
{
    #[Override]
    public function execute(ParameterContainer|array|null $parameters = null): ?ResultInterface
    {
        return null;
    }
}
