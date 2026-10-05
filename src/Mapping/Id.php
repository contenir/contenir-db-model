<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

use Attribute;

/**
 * Marks a mapped property as part of the primary key. Set $generated when
 * the database assigns the value on insert (auto-increment / identity).
 * Implies {@see Column} with default naming when no Column is declared.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Id
{
    public function __construct(
        public bool $generated = false,
    ) {}
}
