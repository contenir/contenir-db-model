<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Metadata;

use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Mapping\BelongsTo;
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\HasMany;
use Contenir\Db\Model\Mapping\HasOne;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\ManyToMany;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Db\Model\Mapping\Via;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Metadata\ColumnMapping;
use Contenir\Db\Model\Metadata\ColumnMappingReader;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Metadata\FieldRole;
use Contenir\Db\Model\Metadata\FieldType;
use Contenir\Db\Model\Metadata\JoinTable;
use Contenir\Db\Model\Metadata\MappingValidator;
use Contenir\Db\Model\Metadata\PropertyFieldReader;
use Contenir\Db\Model\Metadata\RelationContext;
use Contenir\Db\Model\Metadata\RelationCriteria;
use Contenir\Db\Model\Metadata\RelationKeys;
use Contenir\Db\Model\Metadata\RelationKind;
use Contenir\Db\Model\Metadata\RelationMetadata;
use Contenir\Db\Model\Metadata\RelationMetadataBuilder;
use ContenirTest\Db\Model\TestAsset\Entity\Membership;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use ContenirTest\Db\Model\TestAsset\Entity\Profile;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\TestAsset\Mapping;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMetadataFactory::class)]
#[CoversClass(ColumnMappingReader::class)]
#[CoversClass(ColumnMapping::class)]
#[CoversClass(PropertyFieldReader::class)]
#[CoversClass(MappingValidator::class)]
#[CoversClass(RelationMetadataBuilder::class)]
#[CoversClass(RelationContext::class)]
#[CoversClass(MappingException::class)]
#[CoversClass(HasMany::class)]
#[CoversClass(Table::class)]
#[CoversClass(Column::class)]
#[CoversClass(Id::class)]
#[CoversClass(Via::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(JoinTable::class)]
#[CoversClass(RelationKeys::class)]
#[CoversClass(RelationCriteria::class)]
#[CoversClass(HasOne::class)]
#[CoversClass(BelongsTo::class)]
#[CoversClass(ManyToMany::class)]
#[Group('unit')]
final class AttributeMetadataFactoryTest extends TestCase
{
    private AttributeMetadataFactory $factory;

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidMappingProvider(): array
    {
        return [
            'unknown class'           => ['NoSuchEntity', 'does not exist'],
            'no table'                => [Mapping\NoTableEntity::class, 'has no #[Table] attribute'],
            'abstract'                => [Mapping\AbstractTableEntity::class, 'must be instantiable'],
            'no identifier'           => [Mapping\NoIdentifierEntity::class, 'declares no #[Id] property'],
            'static column'           => [Mapping\StaticColumnEntity::class, '$shared must not be static'],
            'untyped column'          => [Mapping\UntypedColumnEntity::class, '$value must declare a type'],
            'union type without type' => [Mapping\UnionTypeColumnEntity::class, 'name a converter'],
            'duplicate column'        => [Mapping\DuplicateColumnEntity::class, 'maps column "label" more than once'],
            'composite generated id'  => [Mapping\CompositeGeneratedIdEntity::class, 'generated keys must be the only'],
            'multiple versions'       => [Mapping\MultipleVersionEntity::class, 'more than one #[Version]'],
            'nullable version'        => [Mapping\NullableVersionEntity::class, 'must be typed as non-nullable int'],
            'string version'          => [Mapping\StringVersionEntity::class, 'must be typed as non-nullable int'],
            'readonly generated id'   => [Mapping\ReadonlyGeneratedIdEntity::class, 'must not be readonly'],
            'id and version'          => [Mapping\IdAndVersionEntity::class, 'cannot be both #[Id] and #[Version]'],
            'relation with column'    => [Mapping\RelationWithColumnEntity::class, 'both a relation and a column'],
            'multiple relations'      => [Mapping\MultipleRelationsEntity::class, 'more than one relation attribute'],
            'key count mismatch'      => [
                Mapping\KeyCountMismatchEntity::class,
                'pairs 2 local column(s) [id, region]',
            ],
            'join key count mismatch' => [Mapping\JoinKeyMismatchEntity::class, 'pairs 1 local column(s) [id]'],
            'unknown relation column' => [Mapping\UnknownRelationColumnEntity::class, 'column "owner_id"'],
            'unknown where column'    => [Mapping\UnknownWhereColumnEntity::class, 'column "archived"'],
            'invalid order direction' => [Mapping\InvalidOrderDirectionEntity::class, 'must be ASC or DESC'],
            'unsupported relation'    => [Mapping\CustomRelationEntity::class, 'unsupported attribute'],
        ];
    }

    #[Test]
    public function columnNameDefaultsToPropertyNameAndHonoursOverride(): void
    {
        $metadata = $this->factory->getMetadataFor(User::class);

        static::assertEquals(
            [
                'email'     => new FieldMetadata('email', 'email', new FieldType('string', false)),
                'createdAt' => new FieldMetadata(
                    'createdAt',
                    'created_at',
                    new FieldType(DateTimeImmutable::class, false),
                ),
            ],
            ['email' => $metadata->getField('email'), 'createdAt' => $metadata->getField('createdAt')],
        );
    }

    #[Test]
    public function mapsOnlyAttributedPropertiesToColumns(): void
    {
        $metadata = $this->factory->getMetadataFor(User::class);

        static::assertSame(['id', 'email', 'name', 'created_at', 'version'], $metadata->getColumnNames());
    }

    #[Test]
    public function memoisesMetadataPerClass(): void
    {
        static::assertSame(
            $this->factory->getMetadataFor(User::class),
            $this->factory->getMetadataFor(User::class),
        );
    }

    #[Test]
    public function mutuallyReferencingEntitiesDoNotRecurse(): void
    {
        $order = $this->factory->getMetadataFor(Order::class);
        $user  = $this->factory->getMetadataFor(User::class);

        static::assertSame([User::class, Order::class], [
            $order->getRelation('user')->targetClass,
            $user->getRelation('orders')->targetClass,
        ]);
    }

    #[Test]
    public function readsTableNameAndSchema(): void
    {
        $metadata = $this->factory->getMetadataFor(Membership::class);

        static::assertSame(['memberships', 'crm'], [$metadata->table, $metadata->schema]);
    }

    #[Test]
    public function recordsGeneratedIdentifierAndVersion(): void
    {
        $metadata = $this->factory->getMetadataFor(User::class);

        static::assertEquals(
            [
                'generated' => new FieldMetadata(
                    'id',
                    'id',
                    new FieldType('int', true),
                    FieldRole::GeneratedIdentifier,
                ),
                'version'   => new FieldMetadata('version', 'version', new FieldType('int', false), FieldRole::Version),
            ],
            ['generated' => $metadata->generatedIdentifier, 'version' => $metadata->version],
        );
    }

    #[Test]
    public function recordsNullabilityAndEnumTypes(): void
    {
        $metadata = $this->factory->getMetadataFor(Order::class);

        static::assertEquals(new FieldType(OrderStatus::class, false), $metadata->getField('status')->type);
    }

    #[DataProvider('invalidMappingProvider')]
    #[Test]
    public function rejectsInvalidMapping(string $className, string $message): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage($message);

        /** @var class-string $className */
        $this->factory->getMetadataFor($className);
    }

    #[Test]
    public function resolvesBelongsToWithTargetPrimaryKeyAsDefaultOwnerKey(): void
    {
        $relation = $this->factory->getMetadataFor(Order::class)->getRelation('user');

        static::assertEquals(
            new RelationMetadata('user', RelationKind::BelongsTo, User::class, new RelationKeys(['user_id'], ['id'])),
            $relation,
        );
    }

    #[Test]
    public function resolvesHasManyWithDefaultLocalKeyAndNormalisedOrder(): void
    {
        $relation = $this->factory->getMetadataFor(User::class)->getRelation('orders');

        static::assertEquals(
            new RelationMetadata(
                'orders',
                RelationKind::HasMany,
                Order::class,
                new RelationKeys(['id'], ['user_id']),
                new RelationCriteria([], ['placed_at' => 'DESC']),
            ),
            $relation,
        );
    }

    #[Test]
    public function resolvesHasOne(): void
    {
        $relation = $this->factory->getMetadataFor(User::class)->getRelation('profile');

        static::assertEquals(
            new RelationMetadata(
                'profile',
                RelationKind::HasOne,
                Profile::class,
                new RelationKeys(['id'], ['user_id']),
            ),
            $relation,
        );
    }

    #[Test]
    public function resolvesManyToManyThroughJoinTable(): void
    {
        $relation = $this->factory->getMetadataFor(User::class)->getRelation('tags');

        static::assertEquals(
            new RelationMetadata(
                'tags',
                RelationKind::ManyToMany,
                Tag::class,
                new RelationKeys(['id'], ['id'], new JoinTable('user_tag', ['user_id'], ['tag_id'])),
                new RelationCriteria(['active' => true], ['name' => 'ASC']),
            ),
            $relation,
        );
    }

    #[Test]
    public function supportsCompositeIdentifiersWithReadonlyAndExplicitlyTypedColumns(): void
    {
        $metadata = $this->factory->getMetadataFor(Membership::class);

        static::assertEquals(
            [
                'identifier' => ['group_id', 'user_id'],
                'generated'  => null,
                'role'       => new FieldMetadata(
                    'role',
                    'role',
                    new FieldType('string', false),
                    FieldRole::Column,
                    true,
                ),
                'meta'       => new FieldMetadata('meta', 'meta', new FieldType(null, false, 'json')),
            ],
            [
                'identifier' => $metadata->getIdentifierColumns(),
                'generated'  => $metadata->generatedIdentifier,
                'role'       => $metadata->getField('role'),
                'meta'       => $metadata->getField('meta'),
            ],
        );
    }

    protected function setUp(): void
    {
        $this->factory = new AttributeMetadataFactory();
    }
}
