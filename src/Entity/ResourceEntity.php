<?php

namespace Contenir\Db\Model\Entity;

use Contenir\Metadata\MetadataInterface;

abstract class BaseResourceEntity extends AbstractEntity implements
    MetadataInterface
{
    protected $routeId;
    protected $routePath;

    public function getResourceId()
    {
        return 'resource';
    }

    /**
     * getRouteId
     *
     * @param  mixed $path
     *
     * @return String
     */
    public function getRouteId(string $path = ''): string
    {
        if ($this->routeId === null) {
            $routeId = sprintf(
                '%s-%s',
                $this->resource_type_id,
                $this->resource_id
            );

            $this->routeId = $routeId;
        }

        return sprintf('%s', join('/', array_filter([$this->routeId, $path])));
    }

    /**
     * getRoutePath
     *
     * @return String
     */
    public function getRoutePath(): string
    {
        if ($this->routePath === null) {
            $parts           = explode('/', (string) $this->slug);
            $this->routePath = sprintf('/%s', join('/', array_filter($parts)));
        }

        return $this->routePath;
    }

    public function getMetaTitle()
    {
        $fallbackTitle = join(' ', array_filter([
            $this->title ?? null,
            $this->subtitle ?? null
        ]));

        return $this->meta_title ?? $fallbackTitle;
    }

    public function getMetaDescription()
    {
        return $this->meta_description ?? $this->description ?? null;
    }

    public function getMetaImage()
    {
        return $this->image[0]->path ?? null;
    }

    public function getMetaModified()
    {
        return $this->updated ?? null;
    }

    public function getMetaPublish()
    {
        return $this->created ?? null;
    }
}
