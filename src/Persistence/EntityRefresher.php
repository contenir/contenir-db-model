<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;

/**
 * Reloads an entity's stored values over its in-memory state and makes
 * them its new snapshot.
 *
 * @internal
 */
final readonly class EntityRefresher
{
    public function __construct(
        private RowFetcher $rows,
        private Session $session,
        private WriteJournal $journal,
    ) {}

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException     When the entity has no key or its row no longer exists.
     * @throws TypeConversionException
     */
    public function refresh(EntityMetadata $metadata, object $entity): void
    {
        $identifier = $this->session->persistedIdentifier($metadata, $entity)
            ?? throw PersistenceException::missingIdentifier('refresh', $metadata->className);
        $row = $this->rows->fetchById($metadata, $identifier)
            ?? throw PersistenceException::rowNotFound($metadata->className, $identifier);

        $this->journal->record($metadata, $entity);
        $this->session->hydrator->refresh($metadata, $entity, $row);
        $this->session->register($metadata, $entity, $this->session->identifiers->fromRow($metadata, $row));
    }
}
