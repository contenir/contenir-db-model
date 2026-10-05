<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Query;

use Contenir\Db\Model\Exception\QueryException;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Query\CriteriaTranslator;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Membership;
use ContenirTest\Db\Model\TestAsset\Entity\Order;
use ContenirTest\Db\Model\TestAsset\Entity\OrderStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Uses the real TypeRegistry and metadata factory: both are final,
 * side-effect-free value producers.
 */
#[CoversClass(CriteriaTranslator::class)]
#[CoversClass(QueryException::class)]
#[Group('unit')]
final class CriteriaTranslatorTest extends TestCase
{
    private CriteriaTranslator $translator;

    private AttributeMetadataFactory $metadata;

    #[Test]
    public function normalisesScalarAndCompositeIdentifiers(): void
    {
        static::assertSame(
            [['id' => 5], ['group_id' => 1, 'user_id' => 2]],
            [
                $this->translator->identifier($this->metadata->getMetadataFor(Order::class), '5'),
                $this->translator->identifier($this->metadata->getMetadataFor(Membership::class), [
                    'userId'  => '2',
                    'groupId' => 1,
                ]),
            ],
        );
    }

    #[Test]
    public function rejectsCompositeIdentifierWithMissingProperty(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('an array with keys [groupId, userId]');

        $this->translator->identifier($this->metadata->getMetadataFor(Membership::class), ['groupId' => 1]);
    }

    #[Test]
    public function rejectsCompositeIdentifierWithWrongProperty(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('an array with keys [groupId, userId]');

        $this->translator->identifier($this->metadata->getMetadataFor(Membership::class), [
            'groupId' => 1,
            'role'    => 'x',
        ]);
    }

    #[Test]
    public function rejectsInvalidDirection(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Cannot order ' . Order::class . ' by "total" sideways');

        $this->translator->order($this->metadata->getMetadataFor(Order::class), ['total' => 'sideways']);
    }

    #[Test]
    public function rejectsNullIdentifierValue(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('a scalar value for $id');

        $this->translator->identifier($this->metadata->getMetadataFor(Order::class), ['id' => null]);
    }

    #[Test]
    public function rejectsUnknownProperty(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('"user_id" is not a mapped column property of ' . Order::class);

        $this->translator->where($this->metadata->getMetadataFor(Order::class), ['user_id' => 1]);
    }

    #[Test]
    public function translatesOrderingToColumnsWithNormalisedDirection(): void
    {
        static::assertSame(
            ['placed_at' => 'DESC', 'total' => 'ASC'],
            $this->translator->order($this->metadata->getMetadataFor(Order::class), [
                'placedAt' => 'desc',
                'total'    => 'Asc',
            ]),
        );
    }

    #[Test]
    public function translatesPropertyCriteriaToColumnsWithDatabaseValues(): void
    {
        $where = $this->translator->where($this->metadata->getMetadataFor(Order::class), [
            'userId'   => '9',
            'status'   => [OrderStatus::Shipped, 'pending'],
            'placedAt' => new DateTimeImmutable('2024-01-02 03:04:05'),
            'id'       => null,
        ]);

        static::assertSame(
            ['user_id' => 9, 'status' => ['shipped', 'pending'], 'placed_at' => '2024-01-02 03:04:05', 'id' => null],
            $where,
        );
    }

    protected function setUp(): void
    {
        $this->translator = new CriteriaTranslator(TypeRegistry::withDefaults());
        $this->metadata   = new AttributeMetadataFactory();
    }
}
