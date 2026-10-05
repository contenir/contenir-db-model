<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

/**
 * @api
 */
class InvalidArgumentException extends SplInvalidArgumentException implements ExceptionInterface {}
