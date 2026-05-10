<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model;

use Contenir\Db\Model\ConfigProvider;
use Contenir\Db\Model\Repository\Factory\RepositoryLookupFactory;
use Contenir\Db\Model\Repository\RepositoryLookup;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    public function testInvocationReturnsExpectedTopLevelKeys(): void
    {
        $config = (new ConfigProvider())();

        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('model', $config);
    }

    public function testDependencyConfigRegistersRepositoryLookupFactory(): void
    {
        $config = (new ConfigProvider())->getDependencyConfig();

        $this->assertSame([], $config['aliases']);
        $this->assertArrayHasKey(RepositoryLookup::class, $config['factories']);
        $this->assertSame(
            RepositoryLookupFactory::class,
            $config['factories'][RepositoryLookup::class]
        );
    }

    public function testDbModelConfigUsesLaminasAdapterByDefault(): void
    {
        $config = (new ConfigProvider())->getDbModelConfig();

        $this->assertSame(Adapter::class, $config['adapter']);
    }
}
