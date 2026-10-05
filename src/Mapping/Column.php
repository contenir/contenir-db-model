<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

use Attribute;

/**
 * Maps a property to a table column. The column name defaults to the
 * property name; the type defaults to the property's declared type and
 * names a registered type converter when given explicitly.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
    ) {}
}
