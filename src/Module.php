<?php

namespace Contenir\Db\Model;

class Module
{
    /**
     * Retrieve default laminas-paginator config for laminas-mvc context.
     *
     * @return array
     */
    public function getConfig(): array
    {
        $provider = new ConfigProvider();

        return [
            'service_manager' => $provider->getDependencyConfig(),
            'model'           => $provider->getDbModelConfig()
        ];
    }
}
