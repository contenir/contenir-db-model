<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

/**
 * Thrown when an optimistic-locked update affects zero rows, indicating
 * that the entity's version no longer matches the persisted row (i.e. it
 * was concurrently updated or deleted by another writer).
 */
class StaleEntityException extends RuntimeException implements ExceptionInterface
{
}
