<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 */

declare(strict_types=1);

namespace Contenir\Db\Model\Repository\Factory;

use Contenir\Db\Model\Repository\RepositoryLookup;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function str_ends_with;
use function str_replace;
use function strlen;
use function substr;

class RepositoryFactory implements FactoryInterface
{
    /**
     * @param string     $requestedName
     * @param array|null $options
     * @return mixed
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        $config = $container->get('config')['model'];

        $dbAdapterClass = $config['adapter'] ?? null;
        $dbAdapter      = $container->get($dbAdapterClass);

        $entityClass = $this->getEntityClass($config, $requestedName);
        $entity      = $container->get($entityClass);

        $repositoryLookup = $container->get(RepositoryLookup::class);

        return new $requestedName(
            $dbAdapter,
            $entity,
            $repositoryLookup
        );
    }

    /**
     * @param array  $config
     * @param string $requestedName
     */
    protected function getEntityClass($config, $requestedName): string
    {
        $entityClass = $config['map'][$requestedName] ?? null;

        if ($entityClass !== null) {
            return $entityClass;
        }

        // Anchored convention: replace the trailing class-name "Repository"
        // suffix and any "\Repository\" namespace segment, leaving unrelated
        // occurrences (e.g. "RepositoryRegistry") alone.
        $entityClass = str_replace('\\Repository\\', '\\Entity\\', $requestedName);

        if (str_ends_with($entityClass, 'Repository')) {
            $entityClass = substr($entityClass, 0, -strlen('Repository')) . 'Entity';
        }

        return $entityClass;
    }
}
