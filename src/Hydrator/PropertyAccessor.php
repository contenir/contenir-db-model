<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Hydrator;

use Closure;
use Contenir\Db\Model\Exception\HydrationException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Instantiates entities without their constructor and reads or writes
 * properties of any visibility. Reflection is created against each
 * property's declaring class, which is what allows readonly properties
 * declared on a parent class to be initialised.
 *
 * @internal
 */
final class PropertyAccessor
{
    /**
     * @var array<class-string, array<string, ReflectionProperty>>
     */
    private array $properties = [];

    /**
     * @throws HydrationException
     */
    public function allowsNull(object $entity, string $property): bool
    {
        return $this->property($entity, $property)->getType()?->allowsNull() ?? true;
    }

    /**
     * @throws HydrationException
     */
    public function get(object $entity, string $property): mixed
    {
        return $this->property($entity, $property)->getValue($entity);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T
     *
     * @throws HydrationException
     */
    public function instantiate(string $className): object
    {
        try {
            return (new ReflectionClass($className))->newInstanceWithoutConstructor();
        } catch (ReflectionException $e) {
            throw HydrationException::cannotInstantiate($className, $e);
        }
    }

    /**
     * @throws HydrationException
     */
    public function isInitialized(object $entity, string $property): bool
    {
        return $this->property($entity, $property)->isInitialized($entity);
    }

    /**
     * @throws HydrationException
     */
    public function isReadOnly(object $entity, string $property): bool
    {
        return $this->property($entity, $property)->isReadOnly();
    }

    /**
     * Return a property to the uninitialised state. Reflection cannot
     * unset, so a closure bound to the declaring class does it.
     *
     * @throws HydrationException
     *
     * @mago-expect analysis:string-member-selector
     */
    public function reset(object $entity, string $property): void
    {
        $declaring = $this->property($entity, $property)->getDeclaringClass()->getName();
        $unset     = function (string $name): void {
            unset($this->{$name});
        };

        Closure::bind($unset, $entity, $declaring)->__invoke($property);
    }

    /**
     * @throws HydrationException
     */
    public function set(object $entity, string $property, mixed $value): void
    {
        $this->property($entity, $property)->setValue($entity, $value);
    }

    /**
     * @throws HydrationException
     */
    private function property(object $entity, string $property): ReflectionProperty
    {
        return $this->properties[$entity::class][$property] ??= $this->reflect($entity, $property);
    }

    /**
     * @throws HydrationException
     */
    private function reflect(object $entity, string $property): ReflectionProperty
    {
        try {
            $declaring = (new ReflectionProperty($entity, $property))->getDeclaringClass()->getName();

            return new ReflectionProperty($declaring, $property);
        } catch (ReflectionException $e) {
            throw HydrationException::inaccessibleProperty($entity::class, $property, $e);
        }
    }
}
