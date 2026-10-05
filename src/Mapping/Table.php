<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

use Attribute;

/**
 * Marks a class as an entity persisted to the named table.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Table
{
    public function __construct(
        public string $name,
        public ?string $schema = null,
    ) {}
}
