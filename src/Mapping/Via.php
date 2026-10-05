<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Mapping;

/**
 * Join table of a {@see ManyToMany} relation.
 *
 * $foreignKey names the join-table column(s) referencing the owning
 * entity's $localKey; $relatedKey names the join-table column(s)
 * referencing the target's $targetKey. Local and target keys default to
 * the respective primary keys.
 *
 * @api
 */
final readonly class Via
{
    /**
     * @param string|list<string>      $foreignKey
     * @param string|list<string>      $relatedKey
     * @param string|list<string>|null $localKey
     * @param string|list<string>|null $targetKey
     */
    public function __construct(
        public string $table,
        public string|array $foreignKey,
        public string|array $relatedKey,
        public string|array|null $localKey = null,
        public string|array|null $targetKey = null,
    ) {}
}
