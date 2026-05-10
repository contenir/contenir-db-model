<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Entity;

use Laminas\EventManager\EventManagerAwareInterface;

/**
 * Contract that the repository, hydrator, and event-driven relation loader
 * rely on. {@see AbstractEntity} is the canonical implementation; custom
 * implementations must support the full interface — partial implementations
 * will not work as repository entity prototypes.
 */
interface EntityInterface extends EventManagerAwareInterface
{
    /**
     * Return the entity's primary-key columns keyed by name. Missing
     * keys (e.g. an auto-increment PK that hasn't been assigned yet) are
     * surfaced as null rather than omitted.
     *
     * @return array<string, mixed>
     */
    public function getPrimaryKeys(): array;

    /**
     * Return the list of column names declared by the entity.
     *
     * @return string[]
     */
    public function getColumns(): array;

    /**
     * Return the relation definitions declared by the entity, keyed by
     * relation name.
     *
     * @return array<string, array>
     */
    public function getRelations(): array;

    /**
     * Return a copy of the row data, keyed by column (and relation) name.
     *
     * @return array<string, mixed>
     */
    public function getArrayCopy(): array;

    /**
     * Return the subset of {@see self::getArrayCopy()} that has been
     * modified since the entity was last marked clean.
     *
     * @return array<string, mixed>
     */
    public function getModifiedArrayCopy(): array;

    /**
     * Apply the row data on top of the existing entity state.
     */
    public function populate(iterable $rowData): self;

    /**
     * Replace the entity's data with $array and mark the result as the
     * canonical state of the row (no modifications outstanding).
     */
    public function synch(iterable $array): AbstractEntity;

    /**
     * Drop any modification flags so the current data is treated as the
     * canonical state of the row.
     */
    public function markClean(): self;

    /**
     * Name of the optimistic-locking version column, or null when this
     * entity opts out of optimistic locking.
     */
    public function getVersionColumn(): ?string;

    /**
     * Compute the next version value given the current one.
     */
    public function nextVersion(mixed $current): mixed;
}
