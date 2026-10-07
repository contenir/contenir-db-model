<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Integration\Persistence;

use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Persistence\RowFetcher;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;
use ContenirTest\Db\Model\Trait\TestDatabaseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RowFetcher::class)]
#[Group('integration')]
final class RowFetcherTest extends TestCase
{
    use TestDatabaseTrait;

    #[Test]
    public function fetchByIdAsksTheDatabaseForASingleRow(): void
    {
        $fetcher = new RowFetcher($this->adapter);

        $fetcher->fetchById((new AttributeMetadataFactory())->getMetadataFor(Tag::class), ['id' => 1]);

        $profile = $this->profiler->getLastProfile();
        static::assertNotNull($profile);
        static::assertSame(1, $profile['parameters']?->getNamedArray()['limit'] ?? null);
    }

    protected function setUp(): void
    {
        $this->setUpTestDatabase("INSERT INTO tags VALUES (1, 'a', TRUE)");
    }
}
