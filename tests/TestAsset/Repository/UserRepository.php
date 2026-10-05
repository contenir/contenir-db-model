<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\TestAsset\Repository;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Repository;
use ContenirTest\Db\Model\TestAsset\Entity\User;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, User::class);
    }

    public function findByEmailDomain(string $domain): array
    {
        $select = $this->createSelect();
        $select->where->like('email', "%@{$domain}");

        return $this->fetch($select);
    }
}
