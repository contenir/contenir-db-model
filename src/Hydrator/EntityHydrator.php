<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Hydrator;

use Contenir\Db\Model\Entity\AbstractEntity;
use Laminas\Hydrator\ObjectPropertyHydrator;

/**
 * Hydrates {@see AbstractEntity} instances by routing the row data through
 * {@see AbstractEntity::synch()} so the resulting entity treats the loaded
 * values as canonical state (no modification flags set). For non-entity
 * targets, falls back to the standard ObjectPropertyHydrator behaviour.
 */
class EntityHydrator extends ObjectPropertyHydrator
{
    public function hydrate(array $data, object $object): object
    {
        if ($object instanceof AbstractEntity) {
            $object->synch($data);

            return $object;
        }

        return parent::hydrate($data, $object);
    }

    public function extract(object $object): array
    {
        if ($object instanceof AbstractEntity) {
            return $object->getArrayCopy();
        }

        return parent::extract($object);
    }
}
