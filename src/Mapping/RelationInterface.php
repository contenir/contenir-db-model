<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

/**
 * Common shape of the relation attributes, read by the metadata factory.
 *
 * @internal
 */
interface RelationInterface
{
    /**
     * @return array<string, 'ASC'|'DESC'|'asc'|'desc'>
     */
    public function orderBy(): array;

    /**
     * @return class-string
     */
    public function target(): string;

    /**
     * @return array<string, scalar|null>
     */
    public function where(): array;
}
