<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Relation;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\RelationException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;

/**
 * What {@see RelationResolver} needs to load a managed entity's relation:
 * the owning entity manager's initializer and the entity's metadata.
 *
 * @internal
 */
final readonly class RelationBinding
{
    /**
     * @param EntityMetadata<object> $metadata
     */
    public function __construct(
        private RelationInitializer $initializer,
        private EntityMetadata $metadata,
    ) {}

    /**
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException
     * @throws RelationException
     * @throws TypeConversionException
     */
    public function resolve(object $entity, string $name): mixed
    {
        return $this->initializer->resolve($this->metadata, $entity, $name);
    }
}
