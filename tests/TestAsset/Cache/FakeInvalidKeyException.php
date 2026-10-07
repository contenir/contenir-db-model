<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Cache;

use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

final class FakeInvalidKeyException extends RuntimeException implements InvalidArgumentException {}
