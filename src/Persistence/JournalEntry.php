<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Persistence;

/**
 * Tracking state of one entity captured just before a write, so the write
 * can be undone in memory if the surrounding transaction rolls back.
 *
 * @internal
 */
final readonly class JournalEntry
{
    /**
     * @param class-string                                             $className
     * @param array<string, int|float|string|bool>|null                $identifier  managed identifier, null when unmanaged
     * @param array<string, int|float|string|bool|null>|null           $snapshot
     * @param array<string, array{initialized: bool, value: mixed}>    $properties  write-back properties (generated id, version)
     */
    public function __construct(
        public object $entity,
        public string $className,
        public ?array $identifier,
        public ?array $snapshot,
        public array $properties,
    ) {}
}
