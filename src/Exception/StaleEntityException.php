<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

/**
 * Thrown when an optimistically-locked update matches no row, meaning the
 * entity's version no longer matches storage because another writer updated
 * or deleted the row first.
 *
 * @api
 */
final class StaleEntityException extends RuntimeException {}
