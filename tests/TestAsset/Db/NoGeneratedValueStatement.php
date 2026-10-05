<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Db;

use Override;
use PDOStatement;
use PhpDb\Adapter\Driver\Pdo\Result;
use PhpDb\Adapter\Driver\Pdo\Statement;
use PhpDb\Adapter\Driver\ResultInterface;
use PhpDb\Adapter\ParameterContainer;

/**
 * Executes normally but reports no generated value, as a driver without
 * insert-id support (or a misconfigured sequence) would.
 */
final class NoGeneratedValueStatement extends Statement
{
    #[Override]
    public function execute(ParameterContainer|array|null $parameters = null): ?ResultInterface
    {
        $result   = parent::execute($parameters);
        $resource = $result?->getResource();
        if (! $resource instanceof PDOStatement) {
            return $result;
        }

        return (new Result())->initialize($resource, null, $result->getAffectedRows());
    }
}
