<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Entity;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Table;
use DateTimeImmutable;
use LogicException;

/**
 * Has a constructor that must never run during hydration, a private
 * column, and a readonly identifier inherited from its parent.
 */
#[Table('notes')]
final class Note extends AbstractDocument
{
    #[Column]
    private string $body;

    #[Column('created_at')]
    public DateTimeImmutable $createdAt;

    public function __construct()
    {
        throw new LogicException('Hydration must not call the constructor');
    }

    public function body(): string
    {
        return $this->body;
    }
}
