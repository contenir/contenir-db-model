<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Relation;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\RelationException;
use Contenir\Db\Model\Exception\TypeConversionException;

/**
 * Opt-in lazy loading for HasOne and BelongsTo properties. Managed
 * entities leave their single-entity relation properties unset; the first
 * read lands here, loads the related entity and assigns it.
 *
 * HasMany and ManyToMany properties are lazy without this trait (they hold
 * a {@see \Contenir\Db\Model\Collection}).
 *
 * @api
 */
trait LazyRelationsTrait
{
    /**
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException
     * @throws RelationException         When the property is not a relation, or the entity is not managed.
     * @throws TypeConversionException
     */
    public function __get(string $name): mixed
    {
        return RelationResolver::resolve($this, $name);
    }

    /**
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function __isset(string $name): bool
    {
        return RelationResolver::isset($this, $name);
    }
}
