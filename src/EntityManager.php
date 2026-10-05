<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\StaleEntityException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Contenir\Db\Model\Persistence\EntityPersister;
use Contenir\Db\Model\Persistence\EntityRefresher;
use Contenir\Db\Model\Persistence\EntityRemover;
use Contenir\Db\Model\Persistence\RowFetcher;
use Contenir\Db\Model\Persistence\Session;
use Contenir\Db\Model\Persistence\StatementRunner;
use Contenir\Db\Model\Persistence\TransactionManager;
use Contenir\Db\Model\Persistence\WriteJournal;
use Contenir\Db\Model\Type\TypeRegistry;
use PhpDb\Adapter\AdapterInterface;
use Throwable;

/**
 * Entry point for persisting entities. Owns the identity map and change
 * tracking for one unit of work: create one per request, and call
 * {@see self::clear()} between jobs in long-running processes.
 *
 * @api
 */
final class EntityManager
{
    private readonly MetadataFactoryInterface $metadata;

    private readonly Session $session;

    private readonly EntityPersister $persister;

    private readonly EntityRemover $remover;

    private readonly EntityRefresher $refresher;

    private readonly TransactionManager $transactions;

    public function __construct(
        AdapterInterface $adapter,
        ?MetadataFactoryInterface $metadata = null,
        ?TypeRegistry $types = null,
    ) {
        $types              ??= TypeRegistry::withDefaults();
        $this->metadata     = $metadata ?? new AttributeMetadataFactory();
        $this->session      = Session::create($types);
        $journal            = new WriteJournal($this->session);
        $statements         = new StatementRunner($adapter);
        $this->persister    = new EntityPersister($statements, $this->session, $types, $journal);
        $this->remover      = new EntityRemover($statements, $this->session, $journal);
        $this->refresher    = new EntityRefresher(new RowFetcher($adapter), $this->session, $journal);
        $this->transactions = new TransactionManager($adapter, $journal);
    }

    /**
     * Stop managing every entity. Objects already handed out keep their
     * values but are no longer tracked; loading their rows again creates
     * new instances.
     */
    public function clear(): void
    {
        $this->session->clear();
    }

    /**
     * Whether the entity is managed (loaded or saved, and not deleted or
     * cleared).
     */
    public function contains(object $entity): bool
    {
        return $this->session->identityMap->contains($entity);
    }

    /**
     * Delete the entity's row and stop managing it.
     *
     * @throws HydrationException
     * @throws MappingException
     * @throws PersistenceException
     * @throws StaleEntityException When a #[Version] check fails.
     * @throws TypeConversionException
     */
    public function delete(object $entity): void
    {
        $this->remover->delete($this->metadataOf($entity), $entity);
    }

    /**
     * Overwrite the entity with its stored values, discarding unsaved
     * changes.
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException When the row no longer exists.
     * @throws TypeConversionException
     */
    public function refresh(object $entity): void
    {
        $this->refresher->refresh($this->metadataOf($entity), $entity);
    }

    /**
     * Insert the entity if it is not managed yet, otherwise update the
     * columns that changed since it was loaded or last saved.
     *
     * @throws HydrationException
     * @throws IdentityConflictException When a different instance with the same key is managed.
     * @throws MappingException
     * @throws PersistenceException
     * @throws StaleEntityException      When a #[Version] check fails.
     * @throws TypeConversionException
     */
    public function save(object $entity): void
    {
        $this->persister->save($this->metadataOf($entity), $entity);
    }

    /**
     * {@see self::save()} followed by a reload of the stored row, in one
     * transaction, so database defaults and trigger results land on the
     * entity.
     *
     * @throws MappingException
     * @throws Throwable Persistence and driver errors, after rolling back.
     */
    public function saveAndRefresh(object $entity): void
    {
        $metadata = $this->metadataOf($entity);

        $this->transactions->transactional(
            /**
             * @throws HydrationException
             * @throws IdentityConflictException
             * @throws PersistenceException
             * @throws StaleEntityException
             * @throws TypeConversionException
             */
            function () use ($metadata, $entity): void {
                $this->persister->save($metadata, $entity);
                $this->refresher->refresh($metadata, $entity);
            },
        );
    }

    /**
     * Run $work in a transaction. Nested calls join the outer transaction.
     * On rollback, the in-memory tracking of entities written inside it is
     * restored to match the database.
     *
     * @template R
     *
     * @param callable(): R $work
     *
     * @return R
     *
     * @throws Throwable Whatever $work throws, after rolling back.
     */
    public function transactional(callable $work): mixed
    {
        return $this->transactions->transactional($work);
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return EntityMetadata<T>
     *
     * @throws MappingException
     */
    private function metadataOf(object $entity): EntityMetadata
    {
        return $this->metadata->getMetadataFor($entity::class);
    }
}
