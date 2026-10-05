<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit;

use Contenir\Db\Model\Collection;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;
use ContenirTest\Db\Model\TestAsset\Factory\EntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function iterator_to_array;

#[CoversClass(Collection::class)]
#[Group('unit')]
final class CollectionTest extends TestCase
{
    #[Test]
    public function collectionOfItemsIsLoaded(): void
    {
        $tag        = EntityFactory::tag();
        $collection = Collection::of([$tag]);

        static::assertSame(
            [true, [$tag], $tag, false, 1, [$tag]],
            [
                $collection->isLoaded(),
                $collection->toArray(),
                $collection->first(),
                $collection->isEmpty(),
                count($collection),
                iterator_to_array($collection),
            ],
        );
    }

    #[Test]
    public function emptyCollectionHasNoFirstItem(): void
    {
        /** @var Collection<Tag> $collection */
        $collection = Collection::of([]);

        static::assertSame([null, true], [$collection->first(), $collection->isEmpty()]);
    }

    #[Test]
    public function lazyCollectionLoadsOnFirstUseOnly(): void
    {
        $calls      = 0;
        $tag        = EntityFactory::tag();
        $collection = Collection::lazy(static function () use (&$calls, $tag): array {
            ++$calls;

            return [$tag];
        });
        $before = $collection->isLoaded();
        $collection->toArray();
        $collection->count();

        static::assertSame([false, true, 1], [$before, $collection->isLoaded(), $calls]);
    }
}
