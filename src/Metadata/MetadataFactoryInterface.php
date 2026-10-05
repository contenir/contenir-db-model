<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Metadata;

use Contenir\Db\Model\Exception\MappingException;

/**
 * @api
 */
interface MetadataFactoryInterface
{
    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return EntityMetadata<T>
     *
     * @throws MappingException When the class is not a validly mapped entity.
     */
    public function getMetadataFor(string $className): EntityMetadata;
}
