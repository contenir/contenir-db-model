<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

/**
 * @api
 */
enum FieldRole
{
    case Column;
    case Identifier;
    case GeneratedIdentifier;
    case Version;

    public function isIdentifier(): bool
    {
        return self::Identifier === $this || self::GeneratedIdentifier === $this;
    }

    /**
     * Whether the persister writes this property back onto the entity
     * after a successful insert or update.
     */
    public function isWrittenBack(): bool
    {
        return self::GeneratedIdentifier === $this || self::Version === $this;
    }
}
