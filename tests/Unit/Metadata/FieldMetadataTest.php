<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Metadata;

use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Metadata\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldMetadata::class)]
#[Group('unit')]
final class FieldMetadataTest extends TestCase
{
    #[Test]
    public function fieldIsWritableByDefault(): void
    {
        static::assertFalse((new FieldMetadata('name', 'name', new FieldType('string', false)))->readonly);
    }
}
