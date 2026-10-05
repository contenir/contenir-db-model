<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\StaleEntityException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Type\TypeRegistry;

/**
 * Issues the INSERT and UPDATE statements for single entities and
 * keeps the session in step with what was written.
 *
 * @internal
 */
final readonly class EntityPersister
{
    public function __construct(
        private StatementRunner $statements,
        private Session $session,
        private TypeRegistry $types,
        private WriteJournal $journal,
    ) {}

    /**
     * Insert every initialised column. A generated identifier that is still
     * null is left to the database and written back afterwards.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function insert(EntityMetadata $metadata, object $entity): void
    {
        $values    = $this->session->hydrator->extract($metadata, $entity);
        $generated = $metadata->generatedIdentifier;
        if (null !== $generated && null !== ($values[$generated->columnName] ?? null)) {
            $generated = null;
        }

        if (null !== $generated) {
            unset($values[$generated->columnName]);
        }

        $identifier = null === $generated ? $this->session->requireIdentifier('insert', $metadata, $entity) : null;

        $this->journal->record($metadata, $entity);
        $raw = $this->statements->insert($metadata->getTableIdentifier(), $values)->getGeneratedValue();
        if (null !== $generated) {
            $this->assignGenerated($metadata, $entity, $generated, $raw);
        }

        $this->session->register(
            $metadata,
            $entity,
            $identifier ?? $this->session->requireIdentifier('insert', $metadata, $entity),
        );
    }

    /**
     * Update a managed entity; insert anything else.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws StaleEntityException
     * @throws TypeConversionException
     */
    public function save(EntityMetadata $metadata, object $entity): void
    {
        if ($this->session->identityMap->contains($entity)) {
            $this->update($metadata, $entity);

            return;
        }

        $this->insert($metadata, $entity);
    }

    /**
     * Write the columns changed since the last snapshot. Nothing is sent
     * when nothing changed. With a #[Version] field the update is guarded
     * by the loaded version and bumps it.
     *
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws PersistenceException
     * @throws StaleEntityException
     * @throws TypeConversionException
     */
    public function update(EntityMetadata $metadata, object $entity): void
    {
        $changes = $this->session->tracker->changes($metadata, $entity);
        if ([] === $changes) {
            return;
        }

        $identifier = $this->session->requireIdentifier('update', $metadata, $entity);
        $where      = $this->session->persistedIdentifier($metadata, $entity) ?? [];
        $lock       = VersionLock::from($metadata, $this->session->tracker->snapshotOf($entity));
        if (null !== $lock) {
            $where                             += $lock->predicate();
            $changes[$lock->field->columnName] = $lock->next();
        }

        $this->journal->record($metadata, $entity);
        $affected = $this->statements->update($metadata->getTableIdentifier(), $changes, $where);
        if (null !== $lock) {
            $lock->verify('update', $metadata->className, $where, $affected);
            $this->session->hydrator->assign($entity, $lock->field->propertyName, $lock->next());
        }

        $this->session->register($metadata, $entity, $identifier);
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    private function assignGenerated(
        EntityMetadata $metadata,
        object $entity,
        FieldMetadata $generated,
        string|int|false|null $raw,
    ): void {
        if (null === $raw || false === $raw) {
            throw PersistenceException::missingGeneratedValue($metadata->className, $generated->propertyName);
        }

        $this->session->hydrator->assign($entity, $generated->propertyName, $this->types->toPhp($generated, $raw));
    }
}
