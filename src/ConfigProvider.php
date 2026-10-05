<?php

declare(strict_types=1);

namespace Contenir\Db\Model;

/**
 * Service wiring for PSR-11 containers via the Laminas component installer
 * or a Mezzio-style config aggregator.
 *
 * @api
 */
final readonly class ConfigProvider
{
    /**
     * @return array<string, array<string, string>>
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array{dependencies: array<string, array<string, string>>}
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }
}
