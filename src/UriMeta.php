<?php

namespace Chuoke\UriMeta;

use League\Uri\Uri;

class UriMeta
{
    /** @var \League\Uri\Uri */
    protected $uri;

    protected $meta;

    public function __construct($uri, array $meta = [])
    {
        if (! $uri instanceof Uri) {
            $uri = Uri::createFromString($uri);
        }

        $this->uri = $uri;
        $this->meta = $meta;
    }

    public function getMeta($key = null)
    {
        if (is_null($key)) {
            return $this->meta;
        }

        return $this->meta[$key] ?? null;
    }

    public function uri(): string
    {
        return (string) $this->uri;
    }

    public function host(): ?string
    {
        return $this->uri->getHost();
    }

    public function scheme(): ?string
    {
        return $this->uri->getScheme();
    }

    public function title(): string
    {
        return $this->getMeta(__FUNCTION__) ?: $this->defaultTitle();
    }

    public function defaultTitle(): string
    {
        return (string) $this->host();
    }

    public function description(): string
    {
        return (string) $this->getMeta(__FUNCTION__) ?: '';
    }

    public function keywords(): array
    {
        return $this->getMeta(__FUNCTION__) ?: [];
    }

    public function icons(): array
    {
        return $this->getMeta(__FUNCTION__) ?: [];
    }

    public function og(): array
    {
        return $this->getMeta(__FUNCTION__) ?: [];
    }

    public function twitter(): array
    {
        return $this->getMeta(__FUNCTION__) ?: [];
    }

    public function toArray(): array
    {
        return array_merge($this->meta, [
            'uri' => $this->uri(),
            'host' => $this->host(),
            'scheme' => $this->scheme(),
            'title' => $this->title(),
        ]);
    }

    public function __get($name)
    {
        return $this->getMeta($name);
    }

    public function __call($name, $arguments)
    {
        return $this->getMeta($name);
    }
}
