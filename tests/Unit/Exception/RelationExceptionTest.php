<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Exception;

use Contenir\Db\Model\Exception\RelationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelationException::class)]
#[Group('unit')]
final class RelationExceptionTest extends TestCase
{
    #[Test]
    public function missingRelatedDescribesRemedy(): void
    {
        static::assertSame(
            'Relation Entity::$owner found no related row, but the property is not nullable; '
                . 'check the foreign key or declare the property nullable',
            RelationException::missingRelated('Entity', 'owner')->getMessage(),
        );
    }

    #[Test]
    public function notLoadedDescribesRemedy(): void
    {
        static::assertSame(
            'Relation Entity::$owner is not loaded: the entity is not managed, so preload() it '
                . 'or load the entity through a repository',
            RelationException::notLoaded('Entity', 'owner')->getMessage(),
        );
    }
}
