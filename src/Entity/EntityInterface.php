<?php

namespace Contenir\Db\Model\Entity;

interface EntityInterface
{
    /**
     * Returns an array of the row primary keys/values
     *
     * @return array
     */
    public function getPrimaryKeys();
}
