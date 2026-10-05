<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

/**
 * @api
 */
enum RelationKind
{
    case HasOne;
    case HasMany;
    case BelongsTo;
    case ManyToMany;

    /**
     * Whether the relation resolves to a collection of entities rather
     * than a single entity (or null).
     */
    public function isCollection(): bool
    {
        return match ($this) {
            self::HasMany, self::ManyToMany => true,
            self::HasOne, self::BelongsTo => false,
        };
    }
}
