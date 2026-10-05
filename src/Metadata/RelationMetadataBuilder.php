<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\HasOne;
use Contenir\Db\Model\Mapping\ManyToMany;
use Contenir\Db\Model\Mapping\RelationInterface;
use ReflectionAttribute;

use function array_keys;
use function array_values;
use function count;

/**
 * Resolves the relation attributes of an entity into {@see RelationMetadata},
 * filling key defaults from primary keys and checking every referenced
 * column is mapped. Target entities are inspected through
 * {@see ColumnMappingReader} only, so mutually-referencing entities do not
 * recurse.
 *
 * @internal
 */
final readonly class RelationMetadataBuilder
{
    public function __construct(
        private ColumnMappingReader $reader,
    ) {}

    /**
     * @param string|list<string>|null $key
     *
     * @return list<string>|null
     */
    private static function columns(string|array|null $key): ?array
    {
        return null === $key ? null : array_values((array) $key);
    }

    /**
     * @throws MappingException
     */
    private static function criteria(RelationContext $context, RelationInterface $attribute): RelationCriteria
    {
        $where = $attribute->where();
        $context->assertTargetColumns(array_keys($where));

        $orderBy = [];
        foreach ($attribute->orderBy() as $column => $direction) {
            $context->assertTargetColumns([$column]);
            $orderBy[$column] = $context->direction($direction);
        }

        return new RelationCriteria($where, $orderBy);
    }

    private static function inverseKeys(ColumnMapping $owner, HasOne|HasMany $attribute): RelationKeys
    {
        return new RelationKeys(
            self::columns($attribute->localKey) ?? $owner->identifierColumns(),
            self::columns($attribute->foreignKey) ?? [],
        );
    }

    private static function joinedKeys(ColumnMapping $owner, ColumnMapping $target, ManyToMany $attribute): RelationKeys
    {
        $via = $attribute->via;

        return new RelationKeys(
            self::columns($via->localKey) ?? $owner->identifierColumns(),
            self::columns($via->targetKey) ?? $target->identifierColumns(),
            new JoinTable($via->table, self::columns($via->foreignKey) ?? [], self::columns($via->relatedKey) ?? []),
        );
    }

    private static function owningKeys(ColumnMapping $target, BelongsTo $attribute): RelationKeys
    {
        return new RelationKeys(
            self::columns($attribute->foreignKey) ?? [],
            self::columns($attribute->ownerKey) ?? $target->identifierColumns(),
        );
    }

    /**
     * @return array{RelationKind, RelationKeys}
     *
     * @throws MappingException
     */
    private static function resolve(
        ColumnMapping $owner,
        ColumnMapping $target,
        string $name,
        RelationInterface $attribute,
    ): array {
        return match (true) {
            $attribute instanceof HasOne => [RelationKind::HasOne, self::inverseKeys($owner, $attribute)],
            $attribute instanceof HasMany => [RelationKind::HasMany, self::inverseKeys($owner, $attribute)],
            $attribute instanceof BelongsTo => [RelationKind::BelongsTo, self::owningKeys($target, $attribute)],
            $attribute instanceof ManyToMany => [
                RelationKind::ManyToMany,
                self::joinedKeys($owner, $target, $attribute),
            ],
            default => throw MappingException::unsupportedRelation($owner->className, $name, $attribute::class),
        };
    }

    /**
     * @return array<string, RelationMetadata>
     *
     * @throws MappingException
     */
    public function build(ColumnMapping $owner): array
    {
        $relations = [];
        foreach (ColumnMappingReader::reflect($owner->className)->getProperties() as $property) {
            $attributes = $property->getAttributes(RelationInterface::class, ReflectionAttribute::IS_INSTANCEOF);
            if ([] === $attributes) {
                continue;
            }

            $name = $property->getName();
            if (count($attributes) > 1) {
                throw MappingException::multipleRelations($owner->className, $name);
            }

            $relations[$name] = $this->relationFor($owner, $name, $attributes[0]->newInstance());
            RelationPropertyValidator::assertValid($owner->className, $property, $relations[$name]);
        }

        return $relations;
    }

    /**
     * @throws MappingException
     */
    private function relationFor(ColumnMapping $owner, string $name, RelationInterface $attribute): RelationMetadata
    {
        $target = $this->reader->read($attribute->target());
        [$kind, $keys] = self::resolve($owner, $target, $name, $attribute);

        $context = new RelationContext($owner, $target, $name);
        $context->assertPaired($keys->localColumns, $keys->targetColumns);
        if (null !== $keys->joinTable) {
            $context->assertPaired($keys->localColumns, $keys->joinTable->localColumns);
            $context->assertPaired($keys->targetColumns, $keys->joinTable->targetColumns);
        }

        $context->assertOwnerColumns($keys->localColumns);
        $context->assertTargetColumns($keys->targetColumns);

        return new RelationMetadata(
            $name,
            $kind,
            $target->className,
            $keys,
            self::criteria($context, $attribute),
        );
    }
}
