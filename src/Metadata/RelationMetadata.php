<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

/**
 * Normalised description of one relation property.
 *
 * @api
 */
final readonly class RelationMetadata
{
    /**
     * @param class-string $targetClass
     */
    public function __construct(
        public string $name,
        public RelationKind $kind,
        public string $targetClass,
        public RelationKeys $keys,
        public RelationCriteria $criteria = new RelationCriteria(),
    ) {}

    public function isCollection(): bool
    {
        return $this->kind->isCollection();
    }
}
