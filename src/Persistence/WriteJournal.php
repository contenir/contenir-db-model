<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Metadata\EntityMetadata;

use function array_reverse;

/**
 * Records each entity's tracking state before it is written inside a
 * transaction. On rollback the entries are replayed in reverse so the
 * session matches the database again: inserted entities become new again
 * (with generated identifiers reset), updated ones regain their previous
 * snapshot and version, and deleted ones are managed again. Property edits
 * made by the caller are left untouched so the work can be retried.
 *
 * @internal
 */
final class WriteJournal
{
    /**
     * @var list<JournalEntry>|null null when no transaction is being journaled
     */
    private ?array $entries = null;

    public function __construct(
        private readonly Session $session,
    ) {}

    public function begin(): void
    {
        $this->entries = [];
    }

    public function commit(): void
    {
        $this->entries = null;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param T                 $entity
     *
     * @throws HydrationException
     */
    public function record(EntityMetadata $metadata, object $entity): void
    {
        if (null === $this->entries) {
            return;
        }

        $properties = [];
        foreach ([$metadata->generatedIdentifier, $metadata->version] as $field) {
            if (null === $field) {
                continue;
            }

            $initialized                      = $this->session->accessor->isInitialized($entity, $field->propertyName);
            $properties[$field->propertyName] = [
                'initialized' => $initialized,
                'value'       => $initialized ? $this->session->accessor->get($entity, $field->propertyName) : null,
            ];
        }

        $this->entries[] = new JournalEntry(
            $entity,
            $metadata->className,
            $this->session->identityMap->identifierOf($entity),
            $this->session->tracker->snapshotOf($entity),
            $properties,
        );
    }

    /**
     * @throws HydrationException
     * @throws IdentityConflictException
     */
    public function rollback(): void
    {
        $entries       = $this->entries ?? [];
        $this->entries = null;

        foreach (array_reverse($entries) as $entry) {
            $this->undo($entry);
        }
    }

    /**
     * @param array{initialized: bool, value: mixed} $state
     *
     * @throws HydrationException
     */
    private function restoreProperty(object $entity, string $property, array $state): void
    {
        if (! $state['initialized']) {
            $this->session->accessor->reset($entity, $property);

            return;
        }

        $this->session->accessor->set($entity, $property, $state['value']);
    }

    /**
     * @throws HydrationException
     * @throws IdentityConflictException
     */
    private function undo(JournalEntry $entry): void
    {
        $this->session->detach($entry->entity);

        foreach ($entry->properties as $property => $state) {
            $this->restoreProperty($entry->entity, $property, $state);
        }

        if (null !== $entry->identifier) {
            $this->session->identityMap->add($entry->className, $entry->identifier, $entry->entity);
        }

        $this->session->tracker->restore($entry->entity, $entry->snapshot);
    }
}
