<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Hydrator;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Hydrator\PropertyAccessor;
use ContenirTest\Db\Model\TestAsset\Entity\Note;
use ContenirTest\Db\Model\TestAsset\Entity\User;
use ContenirTest\Db\Model\TestAsset\Entity\Widget;
use ContenirTest\Db\Model\TestAsset\Mapping\UntypedColumnEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PropertyAccessor::class)]
#[CoversClass(HydrationException::class)]
#[Group('unit')]
final class PropertyAccessorTest extends TestCase
{
    private PropertyAccessor $accessor;

    #[Test]
    public function allowsNullForUntypedProperty(): void
    {
        static::assertTrue($this->accessor->allowsNull(new UntypedColumnEntity(), 'value'));
    }

    #[Test]
    public function initialisesReadonlyPropertyDeclaredOnParentClass(): void
    {
        $note = $this->accessor->instantiate(Note::class);
        $this->accessor->set($note, 'id', 7);

        static::assertSame([7, true], [$note->id, $this->accessor->isReadOnly($note, 'id')]);
    }

    #[Test]
    public function instantiatesWithoutCallingConstructor(): void
    {
        static::assertInstanceOf(Note::class, $this->accessor->instantiate(Note::class));
    }

    #[Test]
    public function readsSameNamedPropertyOfDifferentClassesRepeatedly(): void
    {
        $user       = new User();
        $user->id   = 1;
        $widget     = new Widget();
        $widget->id = 2;

        static::assertSame(
            [1, 2, 1, 2],
            [
                $this->accessor->get($user, 'id'),
                $this->accessor->get($widget, 'id'),
                $this->accessor->get($user, 'id'),
                $this->accessor->get($widget, 'id'),
            ],
        );
    }

    #[Test]
    public function rejectsClassThatCannotBeInstantiated(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Cannot instantiate entity "NoSuchEntity"');

        /** @var class-string $className */
        $className = 'NoSuchEntity';
        $this->accessor->instantiate($className);
    }

    #[Test]
    public function rejectsUnknownProperty(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Cannot access property ' . Note::class . '::$missing');

        $this->accessor->get($this->accessor->instantiate(Note::class), 'missing');
    }

    #[Test]
    public function reportsInitialisationState(): void
    {
        $note   = $this->accessor->instantiate(Note::class);
        $before = $this->accessor->isInitialized($note, 'body');
        $this->accessor->set($note, 'body', 'hello');

        static::assertSame([false, true], [$before, $this->accessor->isInitialized($note, 'body')]);
    }

    #[Test]
    public function reportsWhetherPropertyTypeAllowsNull(): void
    {
        $user = new User();

        static::assertSame([true, false], [
            $this->accessor->allowsNull($user, 'profile'),
            $this->accessor->allowsNull($user, 'email'),
        ]);
    }

    #[Test]
    public function resetReturnsPropertyToUninitialised(): void
    {
        $widget     = new Widget();
        $widget->id = 5;

        $this->accessor->reset($widget, 'id');

        static::assertFalse($this->accessor->isInitialized($widget, 'id'));
    }

    #[Test]
    public function writesAndReadsPrivateProperty(): void
    {
        $note = $this->accessor->instantiate(Note::class);
        $this->accessor->set($note, 'body', 'hello');

        static::assertSame(['hello', 'hello'], [$note->body(), $this->accessor->get($note, 'body')]);
    }

    protected function setUp(): void
    {
        $this->accessor = new PropertyAccessor();
    }
}
