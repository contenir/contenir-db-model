<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Identity;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
use Contenir\Db\Model\Identity\IdentifierResolver;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Membership;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Uses the real TypeRegistry and metadata factory: both are final,
 * side-effect-free value producers.
 */
#[CoversClass(IdentifierResolver::class)]
#[CoversClass(HydrationException::class)]
#[Group('unit')]
final class IdentifierResolverTest extends TestCase
{
    private IdentifierResolver $resolver;

    private AttributeMetadataFactory $metadata;

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rowWithoutIdentifierProvider(): array
    {
        return [
            'column absent' => [['group_id' => 1]],
            'column null'   => [['group_id' => 1, 'user_id' => null]],
        ];
    }

    #[Test]
    public function entityWithNullIdentifierHasNone(): void
    {
        static::assertNull($this->resolver->fromEntity($this->metadata->getMetadataFor(User::class), new User()));
    }

    #[Test]
    public function entityWithUninitialisedIdentifierHasNone(): void
    {
        $membership = (new PropertyAccessor())->instantiate(Membership::class);

        static::assertNull($this->resolver->fromEntity(
            $this->metadata->getMetadataFor(Membership::class),
            $membership,
        ));
    }

    #[Test]
    public function normalisesRowIdentifierToDatabaseForm(): void
    {
        static::assertSame(
            ['id' => 5],
            $this->resolver->fromRow($this->metadata->getMetadataFor(User::class), ['id' => '5', 'email' => 'x']),
        );
    }

    #[Test]
    public function readsCompositeIdentifierFromRowInDeclarationOrder(): void
    {
        static::assertSame(
            ['group_id' => 1, 'user_id' => 2],
            $this->resolver->fromRow($this->metadata->getMetadataFor(Membership::class), [
                'user_id'  => '2',
                'group_id' => 1,
            ]),
        );
    }

    #[Test]
    public function readsIdentifierFromEntity(): void
    {
        $user     = new User();
        $user->id = 7;

        static::assertSame(
            ['id' => 7],
            $this->resolver->fromEntity($this->metadata->getMetadataFor(User::class), $user),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('rowWithoutIdentifierProvider')]
    #[Test]
    public function rejectsRowWithoutCompleteIdentifier(array $row): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('lacks a non-null value for identifier column(s) [group_id, user_id]');

        $this->resolver->fromRow($this->metadata->getMetadataFor(Membership::class), $row);
    }

    protected function setUp(): void
    {
        $this->resolver = new IdentifierResolver(TypeRegistry::withDefaults(), new PropertyAccessor());
        $this->metadata = new AttributeMetadataFactory();
    }
}
