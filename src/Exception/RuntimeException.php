<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Exception;

use RuntimeException as SplRuntimeException;

/**
 * @api
 */
class RuntimeException extends SplRuntimeException implements ExceptionInterface {}
