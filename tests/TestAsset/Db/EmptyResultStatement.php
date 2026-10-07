<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Db;

use Override;
use PhpDb\Adapter\Driver\Pdo\Statement;
use PhpDb\Adapter\Driver\ResultInterface;
use PhpDb\Adapter\ParameterContainer;

/**
 * Statement prototype whose execution yields a result without rows.
 */
final class EmptyResultStatement extends Statement
{
    #[Override]
    public function execute(ParameterContainer|array|null $parameters = null): ?ResultInterface
    {
        return new EmptyResult();
    }
}
