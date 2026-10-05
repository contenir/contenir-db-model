<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

use Contenir\Db\Model\Exception\StaleEntityException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\FieldMetadata;

use function array_key_exists;

/**
 * Optimistic-lock guard for one write: the version the entity was loaded
 * with goes into the WHERE clause, and an update sets the next version.
 *
 * @internal
 */
final readonly class VersionLock
{
    private function __construct(
        public FieldMetadata $field,
        private int|float|string|bool|null $loaded,
    ) {}

    /**
     * A lock for entities that declare #[Version] and have a persisted
     * snapshot containing it; null otherwise.
     *
     * @template T of object
     *
     * @param EntityMetadata<T>                              $metadata
     * @param array<string, int|float|string|bool|null>|null $snapshot
     */
    public static function from(EntityMetadata $metadata, ?array $snapshot): ?self
    {
        $field = $metadata->version;
        if (null === $field || null === $snapshot || ! array_key_exists($field->columnName, $snapshot)) {
            return null;
        }

        return new self($field, $snapshot[$field->columnName]);
    }

    public function next(): int
    {
        return (int) $this->loaded + 1;
    }

    /**
     * @return array<string, int|float|string|bool|null>
     */
    public function predicate(): array
    {
        return [$this->field->columnName => $this->loaded];
    }

    /**
     * @param array<string, int|float|string|bool|null> $where
     *
     * @throws StaleEntityException When the guarded write matched no row.
     */
    public function verify(string $operation, string $className, array $where, int $affected): void
    {
        if (1 !== $affected) {
            throw StaleEntityException::forWrite($operation, $className, $where, $this->loaded);
        }
    }
}
