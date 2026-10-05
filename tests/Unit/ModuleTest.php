<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit;

use Contenir\Db\Model\ConfigProvider;
use Contenir\Db\Model\Module;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Module::class)]
#[Group('unit')]
final class ModuleTest extends TestCase
{
    #[Test]
    public function exposesProviderServicesUnderServiceManagerKey(): void
    {
        $provider = new ConfigProvider();

        static::assertSame(
            [
                'service_manager'   => $provider->getDependencies(),
                'contenir_db_model' => $provider()['contenir_db_model'],
            ],
            (new Module())->getConfig(),
        );
    }
}
