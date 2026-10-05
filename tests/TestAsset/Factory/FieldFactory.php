<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Factory;

use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Metadata\FieldType;

/**
 * Builds plain column FieldMetadata for converter and hydrator tests.
 */
final class FieldFactory
{
    public static function make(?string $phpType, bool $nullable = false, ?string $typeName = null): FieldMetadata
    {
        return new FieldMetadata('value', 'value_column', new FieldType($phpType, $nullable, $typeName));
    }
}
