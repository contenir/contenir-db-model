<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Metadata\FieldRole;
use Contenir\Db\Model\Metadata\FieldType;
use Contenir\Db\Model\Metadata\RelationKeys;
use Contenir\Db\Model\Metadata\RelationKind;
use Contenir\Db\Model\Metadata\RelationMetadata;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use PhpDb\Sql\TableIdentifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityMetadata::class)]
#[CoversClass(FieldMetadata::class)]
#[CoversClass(RelationMetadata::class)]
#[CoversClass(MappingException::class)]
#[Group('unit')]
final class EntityMetadataTest extends TestCase
{
    /**
     * @return EntityMetadata<User>
     */
    private static function metadata(): EntityMetadata
    {
        $int = new FieldType('int', false);

        return new EntityMetadata(
            User::class,
            'users',
            'app',
            [
                'id'      => new FieldMetadata('id', 'id', $int, FieldRole::GeneratedIdentifier),
                'ownerId' => new FieldMetadata('ownerId', 'owner_id', $int),
                'version' => new FieldMetadata('version', 'version', $int, FieldRole::Version),
            ],
            [
                'orders' => new RelationMetadata(
                    'orders',
                    RelationKind::HasMany,
                    Order::class,
                    new RelationKeys(['id'], ['user_id']),
                ),
            ],
        );
    }

    #[Test]
    public function buildsSchemaQualifiedTableIdentifier(): void
    {
        static::assertEquals(new TableIdentifier('users', 'app'), self::metadata()->getTableIdentifier());
    }

    #[Test]
    public function exposesRelationsByProperty(): void
    {
        $metadata = self::metadata();

        static::assertSame(
            [true, false, true],
            [
                $metadata->hasRelation('orders'),
                $metadata->hasRelation('id'),
                $metadata->getRelation('orders')->isCollection(),
            ],
        );
    }

    #[Test]
    public function indexesIdentifierGeneratedAndVersionFields(): void
    {
        $metadata = self::metadata();

        static::assertSame(
            [['id'], 'id', 'version'],
            [
                $metadata->getIdentifierColumns(),
                $metadata->generatedIdentifier?->propertyName,
                $metadata->version?->propertyName,
            ],
        );
    }

    #[Test]
    public function looksUpFieldsByPropertyAndByColumn(): void
    {
        $metadata = self::metadata();

        static::assertSame(
            [true, false, true, false, 'ownerId', ['id', 'owner_id', 'version']],
            [
                $metadata->hasField('ownerId'),
                $metadata->hasField('owner_id'),
                $metadata->hasColumn('owner_id'),
                $metadata->hasColumn('ownerId'),
                $metadata->getFieldForColumn('owner_id')->propertyName,
                $metadata->getColumnNames(),
            ],
        );
    }

    #[Test]
    public function rejectsUnknownColumn(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Column "missing" is not mapped');

        self::metadata()->getFieldForColumn('missing');
    }

    #[Test]
    public function rejectsUnknownField(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('$missing is not a mapped column');

        self::metadata()->getField('missing');
    }

    #[Test]
    public function rejectsUnknownRelation(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('$missing is not a declared relation');

        self::metadata()->getRelation('missing');
    }
}
