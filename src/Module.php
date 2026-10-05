<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

use Contenir\Db\Model\Container\ModuleConfig;

/**
 * laminas-mvc module: exposes the {@see ConfigProvider} services under the
 * "service_manager" key that laminas-mvc reads.
 *
 * @api
 */
final readonly class Module
{
    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $provider = new ConfigProvider();
        $config   = $provider();

        return [
            'service_manager' => $provider->getDependencies(),
            ModuleConfig::KEY => $config[ModuleConfig::KEY],
        ];
    }
}
