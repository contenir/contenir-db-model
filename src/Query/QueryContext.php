<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Query;

use Contenir\Db\Model\Identity\IdentityMap;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Contenir\Db\Model\Relation\Preloader;

/**
 * The read-side collaborators an {@see \Contenir\Db\Model\EntityManager}
 * shares with its repositories.
 *
 * @internal
 */
final readonly class QueryContext
{
    public function __construct(
        public MetadataFactoryInterface $metadata,
        public EntityReader $reader,
        public CriteriaTranslator $criteria,
        public IdentityMap $identityMap,
        public Preloader $preloader,
    ) {}
}
