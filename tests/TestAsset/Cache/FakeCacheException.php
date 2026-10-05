<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Cache;

use Psr\SimpleCache\CacheException;
use RuntimeException;

final class FakeCacheException extends RuntimeException implements CacheException {}
