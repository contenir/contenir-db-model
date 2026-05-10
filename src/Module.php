<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

class Module
{
    /**
     * Retrieve default laminas-paginator config for laminas-mvc context.
     */
    public function getConfig(): array
    {
        $provider = new ConfigProvider();

        return [
            'service_manager' => $provider->getDependencyConfig(),
            'model'           => $provider->getDbModelConfig(),
        ];
    }
}
