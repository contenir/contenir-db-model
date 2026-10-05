<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

use Attribute;

/**
 * Marks an integer property as the optimistic-locking version counter.
 * Implies {@see Column} with default naming when no Column is declared.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Version {}
