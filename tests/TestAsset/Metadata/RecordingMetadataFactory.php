<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Metadata;

use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;
use Override;

/**
 * Delegates to the attribute reader and remembers which classes were asked
 * for.
 */
final class RecordingMetadataFactory implements MetadataFactoryInterface
{
    /**
     * @var list<class-string>
     */
    public array $requested = [];

    private readonly AttributeMetadataFactory $inner;

    public function __construct()
    {
        $this->inner = new AttributeMetadataFactory();
    }

    #[Override]
    public function getMetadataFor(string $className): EntityMetadata
    {
        $this->requested[] = $className;

        return $this->inner->getMetadataFor($className);
    }
}
