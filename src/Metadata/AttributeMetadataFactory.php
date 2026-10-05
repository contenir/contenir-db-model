<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Override;

use function array_key_exists;

/**
 * Builds {@see EntityMetadata} from the attributes in
 * {@see \Contenir\Db\Model\Mapping}. Results are memoised per class for the
 * lifetime of the factory.
 *
 * @api
 */
final class AttributeMetadataFactory implements MetadataFactoryInterface
{
    /**
     * @var array<class-string, EntityMetadata<object>>
     */
    private array $metadata = [];

    private readonly ColumnMappingReader $reader;

    private readonly RelationMetadataBuilder $relations;

    public function __construct()
    {
        $this->reader    = new ColumnMappingReader();
        $this->relations = new RelationMetadataBuilder($this->reader);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return EntityMetadata<T>
     *
     * @throws MappingException
     */
    #[Override]
    public function getMetadataFor(string $className): EntityMetadata
    {
        if (array_key_exists($className, $this->metadata)) {
            /** @var EntityMetadata<T> */
            return $this->metadata[$className];
        }

        $mapping  = $this->reader->read($className);
        $metadata = new EntityMetadata(
            $className,
            $mapping->table,
            $mapping->schema,
            $mapping->fields,
            $this->relations->build($mapping),
        );

        return $this->metadata[$className] = $metadata;
    }
}
