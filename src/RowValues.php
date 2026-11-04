<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

use Contenir\Db\Model\Exception\HydrationException;
use Contenir\Db\Model\Exception\MappingException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Hydrator\EntityHydrator;
use Contenir\Db\Model\Metadata\MetadataFactoryInterface;

/**
 * Entities from database rows without the database: a new entity built
 * from a row, or a copy of an entity with a row's values in place of its
 * own, each converted exactly as a loaded row would be. Neither is managed
 * by the {@see EntityManager}, and nothing is read or written.
 *
 * For showing a change before it is saved, such as a CMS preview of a
 * record's unsaved values, or for reading an entity's columns by the names
 * a site's configuration gives them:
 *
 *     $values  = new RowValues($em);
 *     $preview = $values->withRow($page, ['title' => 'Spa Rituals', 'active' => 'pending']);
 *
 * @api
 */
final readonly class RowValues
{
    private MetadataFactoryInterface $metadata;

    private EntityHydrator $hydrator;

    public function __construct(EntityManager $em)
    {
        $context        = $em->queryContext();
        $this->metadata = $context->metadata;
        $this->hydrator = $context->hydrator;
    }

    /**
     * A new entity built from a row. Mapped columns absent from the row keep
     * their declared defaults; unmapped columns are ignored. Saving the
     * entity inserts it like any new entity.
     *
     * @template T of object
     *
     * @param class-string<T>      $className
     * @param array<string, mixed> $row       Keyed by column name.
     *
     * @return T
     *
     * @throws HydrationException
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function fromRow(string $className, array $row): object
    {
        return $this->hydrator->hydrate($this->metadata->getMetadataFor($className), $row);
    }

    /**
     * The entity's mapped columns as a row, keyed by column name, each as
     * saving would write it. A property not yet set is left out.
     *
     * @return array<string, mixed>
     *
     * @throws HydrationException
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function of(object $entity): array
    {
        return $this->hydrator->extract($this->metadata->getMetadataFor($entity::class), $entity);
    }

    /**
     * A copy of the entity holding the row's values in place of its own:
     * what the entity would hold once the row were saved. The copy shares
     * the original's relations; the original is left as it was. Unmapped
     * columns are ignored.
     *
     * @template T of object
     *
     * @param T                    $entity
     * @param array<string, mixed> $row    Keyed by column name.
     *
     * @return T
     *
     * @throws HydrationException When the row changes a readonly property.
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function withRow(object $entity, array $row): object
    {
        $copy = clone $entity;
        $this->hydrator->refresh($this->metadata->getMetadataFor($entity::class), $copy, $row);

        return $copy;
    }
}
