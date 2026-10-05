<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Attribute;
use Contenir\Db\Model\Mapping\RelationInterface;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;
use Override;

/**
 * Third-party relation attribute the metadata factory does not understand.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CustomRelation implements RelationInterface
{
    #[Override]
    public function orderBy(): array
    {
        return [];
    }

    #[Override]
    public function target(): string
    {
        return Tag::class;
    }

    #[Override]
    public function where(): array
    {
        return [];
    }
}
