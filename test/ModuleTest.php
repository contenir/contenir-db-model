<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model;

use Contenir\Db\Model\Module;
use Contenir\Db\Model\Repository\RepositoryLookup;
use PHPUnit\Framework\TestCase;

class ModuleTest extends TestCase
{
    public function testGetConfigReturnsMvcStyleConfiguration(): void
    {
        $config = (new Module())->getConfig();

        $this->assertArrayHasKey('service_manager', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey(
            RepositoryLookup::class,
            $config['service_manager']['factories']
        );
    }
}
