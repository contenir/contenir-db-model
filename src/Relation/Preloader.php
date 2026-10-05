<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Relation;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\IdentityConflictException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Exception\RelationException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;

use function array_shift;
use function array_values;
use function explode;
use function spl_object_id;

/**
 * Loads relations for many entities at once: one query per relation per
 * level of a dotted path ("orders.items"), instead of one per entity.
 *
 * @internal
 */
final readonly class Preloader
{
    public function __construct(
        private MetadataFactoryInterface $metadata,
        private RelationLoader $loader,
        private RelationInitializer $initializer,
    ) {}

    /**
     * @param array<string, array<string, mixed>> $tree
     * @param list<string>                        $segments
     *
     * @return array<string, array<string, mixed>>
     */
    private static function insert(array $tree, array $segments): array
    {
        $head = array_shift($segments);
        if (null === $head) {
            return $tree;
        }

        /** @var array<string, array<string, mixed>> $branch */
        $branch      = $tree[$head] ?? [];
        $tree[$head] = self::insert($branch, $segments);

        return $tree;
    }

    /**
     * ["orders.items", "orders.user", "profile"] becomes
     * ["orders" => ["items" => [], "user" => []], "profile" => []].
     *
     * @param list<string> $paths
     *
     * @return array<string, array<string, mixed>>
     */
    private static function tree(array $paths): array
    {
        $tree = [];
        foreach ($paths as $path) {
            $tree = self::insert($tree, explode('.', $path));
        }

        return $tree;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param list<T>           $entities
     * @param list<string>      $paths
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException          When a path names an undeclared relation.
     * @throws PersistenceException
     * @throws RelationException
     * @throws TypeConversionException
     */
    public function preload(EntityMetadata $metadata, array $entities, array $paths): void
    {
        $this->walk($metadata, $entities, self::tree($paths));
    }

    /**
     * @param EntityMetadata<object> $metadata
     * @param list<object>           $entities
     * @param array<string, array<string, mixed>> $tree
     *
     * @throws HydrationException
     * @throws IdentityConflictException
     * @throws MappingException
     * @throws PersistenceException
     * @throws RelationException
     * @throws TypeConversionException
     */
    private function walk(EntityMetadata $metadata, array $entities, array $tree): void
    {
        foreach ($tree as $name => $children) {
            $relation = $metadata->getRelation($name);
            $groups   = $this->loader->load($metadata, $relation, $entities);

            $targets = [];
            foreach ($entities as $entity) {
                $key     = $this->loader->keyOf($metadata, $relation, $entity);
                $related = null === $key ? [] : $groups[$key] ?? [];
                $this->initializer->assign($entity, $relation, $related);
                foreach ($related as $target) {
                    $targets[spl_object_id($target)] = $target;
                }
            }

            if ([] !== $children && [] !== $targets) {
                /** @var array<string, array<string, mixed>> $children */
                $this->walk($this->metadata->getMetadataFor($relation->targetClass), array_values($targets), $children);
            }
        }
    }
}
