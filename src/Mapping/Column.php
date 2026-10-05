<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

use Attribute;

/**
 * Maps a property to a table column. The column name defaults to the
 * property name; the type defaults to the property's declared type and
 * names a registered type converter when given explicitly.
 *
 * Set $sensitive to keep the column's values out of exception messages
 * and other output produced by this library. Properties typed as
 * {@see \Contenir\Db\Model\Value\SensitiveString} are sensitive
 * automatically.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public bool $sensitive = false,
    ) {}
}
