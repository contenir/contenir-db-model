<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Db;

use LogicException;
use Override;
use PhpDb\Adapter\Driver\ResultInterface;
use PhpDb\ResultSet\ResultSetInterface;

/**
 * A result with no rows, as a driver might return for a query that matched
 * nothing.
 */
final class EmptyResult implements ResultInterface
{
    #[Override]
    public function buffer(): void {}

    #[Override]
    public function count(): int
    {
        return 0;
    }

    #[Override]
    public function current(): mixed
    {
        return null;
    }

    #[Override]
    public function getAffectedRows(): int
    {
        return 0;
    }

    #[Override]
    public function getFieldCount(): int
    {
        return 0;
    }

    #[Override]
    public function getGeneratedValue(): string|int|false|null
    {
        return null;
    }

    #[Override]
    public function getQueryResult(?ResultSetInterface $resultPrototype = null): ResultSetInterface
    {
        throw new LogicException('Not supported.');
    }

    #[Override]
    public function getResource(): mixed
    {
        return null;
    }

    #[Override]
    public function isBuffered(): ?bool
    {
        return true;
    }

    #[Override]
    public function isQueryResult(): bool
    {
        return true;
    }

    #[Override]
    public function key(): mixed
    {
        return null;
    }

    #[Override]
    public function next(): void {}

    #[Override]
    public function rewind(): void {}

    #[Override]
    public function valid(): bool
    {
        return false;
    }
}
