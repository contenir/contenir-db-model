<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\StaleEntityException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;

/**
 * Issues the DELETE for a single entity and stops managing it.
 *
 * @internal
 */
final readonly class EntityRemover
{
    public function __construct(
        private StatementRunner $statements,
        private Session $session,
        private WriteJournal $journal,
    ) {}

    /**
     * Delete by the identifier the database holds. With a #[Version] field
     * and a persisted snapshot, the delete is guarded by the loaded
     * version.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     * @throws PersistenceException
     * @throws StaleEntityException
     * @throws TypeConversionException
     */
    public function delete(EntityMetadata $metadata, object $entity): void
    {
        $where = $this->session->persistedIdentifier($metadata, $entity)
            ?? throw PersistenceException::missingIdentifier('delete', $metadata->className);

        $lock = VersionLock::from($metadata, $this->session->tracker->snapshotOf($entity));
        if (null !== $lock) {
            $where += $lock->predicate();
        }

        $this->journal->record($metadata, $entity);
        $affected = $this->statements->delete($metadata->getTableIdentifier(), $where);
        $lock?->verify('delete', $metadata->className, $where, $affected);

        $this->session->detach($entity);
    }
}
