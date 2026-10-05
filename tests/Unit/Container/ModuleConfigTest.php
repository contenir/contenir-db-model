<?php

declare(strict_types=1);

namespace ContenirTest\Db\Model\Unit\Container;

use Contenir\Db\Model\Container\ConfigReader;
use Contenir\Db\Model\Container\ModuleConfig;
use Contenir\Db\Model\Exception\ConfigurationException;
use ContenirTest\Db\Model\TestAsset\Container\InMemoryContainer;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleConfig::class)]
#[CoversClass(ConfigReader::class)]
#[CoversClass(ConfigurationException::class)]
#[Group('unit')]
final class ModuleConfigTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidConfigProvider(): array
    {
        return [
            'module not an array'      => ['nope', '"contenir_db_model." must be an array, got string'],
            'adapter not a string'     => [
                ['adapter' => 5],
                '"contenir_db_model.adapter" must be a service name string, got int',
            ],
            'ttl not an integer'       => [
                ['metadata_cache_ttl' => '60'],
                'must be an integer number of seconds, got string',
            ],
            'types not an array'       => [
                ['types' => 'money'],
                '"contenir_db_model.types" must be an array of name => service',
            ],
            'types entry not a string' => [
                ['types' => ['money' => 5]],
                '"contenir_db_model.types" must be an array of name => service',
            ],
        ];
    }

    #[Test]
    public function defaultsApplyWithoutConfigService(): void
    {
        $config = ModuleConfig::from(new InMemoryContainer());

        static::assertSame(
            [AdapterInterface::class, null, null, []],
            [$config->adapter, $config->metadataCache, $config->metadataCacheTtl, $config->types],
        );
    }

    #[Test]
    public function ignoresNonArrayConfigService(): void
    {
        static::assertSame(
            AdapterInterface::class,
            ModuleConfig::from(new InMemoryContainer(['config' => 'x']))->adapter,
        );
    }

    #[Test]
    public function readsConfiguredValues(): void
    {
        $config = ModuleConfig::from(new InMemoryContainer([
            'config' => [
                'contenir_db_model' => [
                    'adapter'            => 'db.primary',
                    'metadata_cache'     => 'cache.apcu',
                    'metadata_cache_ttl' => 3600,
                    'types'              => ['money' => 'converter.money'],
                ],
            ],
        ]));

        static::assertSame(
            ['db.primary', 'cache.apcu', 3600, ['money' => 'converter.money']],
            [$config->adapter, $config->metadataCache, $config->metadataCacheTtl, $config->types],
        );
    }

    #[DataProvider('invalidConfigProvider')]
    #[Test]
    public function rejectsMalformedConfiguration(mixed $module, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        ModuleConfig::from(new InMemoryContainer(['config' => ['contenir_db_model' => $module]]));
    }
}
