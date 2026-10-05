<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;

use function count;
use function strtoupper;

/**
 * Validation helpers bound to the relation currently being resolved, so
 * error messages can name the owning class and relation property.
 *
 * @internal
 */
final readonly class RelationContext
{
    public function __construct(
        private ColumnMapping $owner,
        private ColumnMapping $target,
        private string $relation,
    ) {}

    /**
     * @param list<string> $columns
     *
     * @throws MappingException
     */
    public function assertOwnerColumns(array $columns): void
    {
        $this->assertMapped($this->owner, $columns);
    }

    /**
     * @param list<string> $local
     * @param list<string> $target
     *
     * @throws MappingException
     */
    public function assertPaired(array $local, array $target): void
    {
        if ([] === $local || count($local) !== count($target)) {
            throw MappingException::keyCountMismatch($this->owner->className, $this->relation, $local, $target);
        }
    }

    /**
     * @param list<string> $columns
     *
     * @throws MappingException
     */
    public function assertTargetColumns(array $columns): void
    {
        $this->assertMapped($this->target, $columns);
    }

    /**
     * @return 'ASC'|'DESC'
     *
     * @throws MappingException
     */
    public function direction(string $direction): string
    {
        return match (strtoupper($direction)) {
            'ASC'   => 'ASC',
            'DESC'  => 'DESC',
            default => throw MappingException::invalidOrderDirection(
                $this->owner->className,
                $this->relation,
                $direction,
            ),
        };
    }

    /**
     * @param list<string> $columns
     *
     * @throws MappingException
     */
    private function assertMapped(ColumnMapping $mapping, array $columns): void
    {
        foreach ($columns as $column) {
            if (! $mapping->hasColumn($column)) {
                throw MappingException::unknownRelationColumn(
                    $this->owner->className,
                    $this->relation,
                    $mapping->className,
                    $column,
                );
            }
        }
    }
}
