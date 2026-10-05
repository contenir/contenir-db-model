<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use Override;

use function count;

/**
 * Read-only list of related entities for a HasMany or ManyToMany
 * property. A lazy collection queries on first use (iteration, count,
 * toArray, first, isEmpty) and keeps the result; {@see self::of()} builds
 * an already-loaded one.
 *
 * Collections do not write anything: add or remove related rows by saving
 * the related entities themselves.
 *
 * @api
 *
 * @template-covariant T of object
 *
 * @implements IteratorAggregate<int, T>
 */
final class Collection implements IteratorAggregate, Countable
{
    /**
     * @var list<T>|null
     */
    private ?array $items;

    /**
     * @param list<T>|null        $items  null until loaded
     * @param Closure(): list<T>  $loader
     */
    private function __construct(
        ?array $items,
        private readonly Closure $loader,
    ) {
        $this->items = $items;
    }

    /**
     * @template E of object
     *
     * @param Closure(): list<E> $loader
     *
     * @return self<E>
     */
    public static function lazy(Closure $loader): self
    {
        return new self(null, $loader);
    }

    /**
     * @template E of object
     *
     * @param list<E> $items
     *
     * @return self<E>
     */
    public static function of(array $items): self
    {
        return new self(
            $items,
            /** @return list<E> */
            static fn(): array => $items,
        );
    }

    #[Override]
    public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * @return T|null
     */
    public function first(): ?object
    {
        return $this->toArray()[0] ?? null;
    }

    /**
     * @return ArrayIterator<int, T>
     */
    #[Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }

    public function isEmpty(): bool
    {
        return [] === $this->toArray();
    }

    public function isLoaded(): bool
    {
        return null !== $this->items;
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        return $this->items ??= ($this->loader)();
    }
}
