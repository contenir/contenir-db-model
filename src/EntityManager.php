<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\StaleEntityException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Identity\EntityLoader;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Contenir\Db\Model\Persistence\EntityPersister;
use Contenir\Db\Model\Persistence\EntityRefresher;
use Contenir\Db\Model\Persistence\EntityRemover;
use Contenir\Db\Model\Persistence\RowFetcher;
use Contenir\Db\Model\Persistence\Session;
use Contenir\Db\Model\Persistence\StatementRunner;
use Contenir\Db\Model\Persistence\TransactionManager;
use Contenir\Db\Model\Persistence\WriteJournal;
use Contenir\Db\Model\Query\CriteriaTranslator;
use Contenir\Db\Model\Query\EntityReader;
use Contenir\Db\Model\Query\QueryContext;
use Contenir\Db\Model\Relation\Preloader;
use Contenir\Db\Model\Relation\RelationInitializer;
use Contenir\Db\Model\Relation\RelationLoader;
use Contenir\Db\Model\Relation\RowGroupKey;
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

    private readonly QueryContext $queryContext;

    /**
     * @var array<class-string, Repository<object>>
     */
    private array $repositories = [];

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
        $relations          = new RelationInitializer($this->session->accessor);
        $this->persister    = new EntityPersister($statements, $this->session, $types, $journal, $relations);
        $this->remover      = new EntityRemover($statements, $this->session, $journal);
        $rows               = new RowFetcher($adapter);
        $this->refresher    = new EntityRefresher($rows, $this->session, $journal, $relations);
        $this->transactions = new TransactionManager($adapter, $journal);
        $loader             = new EntityLoader(
            $this->session->hydrator,
            $this->session->tracker,
            $this->session->identityMap,
            $this->session->identifiers,
            $relations,
        );
        $relationLoader = new RelationLoader(
            $this->metadata,
            $rows,
            $loader,
            $this->session->hydrator,
            new RowGroupKey($types),
        );
        $relations->attach($relationLoader);
        $this->queryContext = new QueryContext(
            $this->metadata,
            new EntityReader($rows, $loader),
            new CriteriaTranslator($types),
            $this->session->identityMap,
            new Preloader($this->metadata, $relationLoader, $relations),
        );
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
        $this->remover->delete($this->metadata->getMetadataFor($entity::class), $entity);
    }

    /**
     * The generic repository for an entity class, created once per
     * manager. Custom repository subclasses are constructed directly (or
     * by a container factory) with this manager.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return Repository<T>
     *
     * @throws MappingException
     */
    public function getRepository(string $className): Repository
    {
        /** @var Repository<T> */
        return $this->repositories[$className] ??= new Repository($this, $className);
    }

    /**
     * @internal Read-side collaborators shared with repositories.
     */
    public function queryContext(): QueryContext
    {
        return $this->queryContext;
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
        $this->refresher->refresh($this->metadata->getMetadataFor($entity::class), $entity);
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
        $this->persister->save($this->metadata->getMetadataFor($entity::class), $entity);
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
        $metadata = $this->metadata->getMetadataFor($entity::class);

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
}
