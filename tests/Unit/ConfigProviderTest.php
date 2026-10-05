<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit;

use Contenir\Db\Model\ConfigProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigProvider::class)]
#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function invocationExposesDependencyConfig(): void
    {
        $provider = new ConfigProvider();

        static::assertSame(['dependencies' => $provider->getDependencies()], $provider());
    }
}
