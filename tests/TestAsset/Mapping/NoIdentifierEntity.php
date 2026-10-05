<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Mapping;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Table;

#[Table('no_identifier')]
final class NoIdentifierEntity
{
    #[Column]
    public string $name;
}
