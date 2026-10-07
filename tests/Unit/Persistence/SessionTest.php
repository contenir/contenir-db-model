<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Persistence;

use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use Contenir\Db\Model\Persistence\Session;
use Contenir\Db\Model\Type\TypeRegistry;
use ContenirTest\Db\Model\TestAsset\Entity\Tag;
use ContenirTest\Db\Model\TestAsset\Factory\EntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
#[Group('unit')]
final class SessionTest extends TestCase
{
    #[Test]
    public function clearForgetsEveryEntityAndItsSnapshot(): void
    {
        $session  = Session::create(TypeRegistry::withDefaults());
        $metadata = (new AttributeMetadataFactory())->getMetadataFor(Tag::class);
        $tag      = EntityFactory::tag();
        $tag->id  = 1;
        $session->register($metadata, $tag, ['id' => 1]);

        $session->clear();

        static::assertSame([false, null], [$session->identityMap->contains($tag), $session->tracker->snapshotOf($tag)]);
    }

    #[Test]
    public function detachForgetsOnlyThatEntityAndItsSnapshot(): void
    {
        $session   = Session::create(TypeRegistry::withDefaults());
        $metadata  = (new AttributeMetadataFactory())->getMetadataFor(Tag::class);
        $tag       = EntityFactory::tag();
        $tag->id   = 1;
        $other     = EntityFactory::tag('other');
        $other->id = 2;
        $session->register($metadata, $tag, ['id' => 1]);
        $session->register($metadata, $other, ['id' => 2]);

        $session->detach($tag);

        static::assertSame(
            [false, null, true],
            [
                $session->identityMap->contains($tag),
                $session->tracker->snapshotOf($tag),
                $session->identityMap->contains($other),
            ],
        );
    }

    #[Test]
    public function persistedIdentifierPrefersSnapshotOverCurrentProperties(): void
    {
        $session  = Session::create(TypeRegistry::withDefaults());
        $metadata = (new AttributeMetadataFactory())->getMetadataFor(Tag::class);
        $tag      = EntityFactory::tag();
        $tag->id  = 1;
        $session->register($metadata, $tag, ['id' => 1]);
        $tag->id = 2;

        static::assertSame(['id' => 1], $session->persistedIdentifier($metadata, $tag));
    }

    #[Test]
    public function snapshotWithoutKeyColumnsYieldsNoPersistedIdentifier(): void
    {
        $session  = Session::create(TypeRegistry::withDefaults());
        $metadata = (new AttributeMetadataFactory())->getMetadataFor(Tag::class);
        $tag      = EntityFactory::tag();
        $session->tracker->restore($tag, ['name' => 'news']);

        static::assertNull($session->persistedIdentifier($metadata, $tag));
    }
}
