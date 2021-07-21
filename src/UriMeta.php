<?php

namespace Chuoke\UriMeta;

use League\Uri\Uri;

class UriMeta
{
    /** @var \League\Uri\Uri */
    protected $uri;

    public array $titles = [];

    public array $descriptions = [];

    public string $keywords = '';

    public array $icons = [];

    public function __construct($uri)
    {
        if (!$uri instanceof Uri) {
            $uri = Uri::createFromString($uri);
        }

        $this->uri = $uri;
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
        return reset($this->titles) ?: $this->defaultTitle();
    }

    public function defaultTitle(): string
    {
        return (string) $this->host();
    }

    public function titles(): array
    {
        return $this->titles;
    }

    public function description(): string
    {
        return (string) reset($this->descriptions);
    }

    public function descriptions(): array
    {
        return $this->descriptions;
    }

    public function keywords(): string
    {
        return $this->keywords;
    }

    public function icon(): string
    {
        return (string) reset($this->icons);
    }

    public function icons(): array
    {
        return $this->icons;
    }

    public function toArray(): array
    {
        return [
            'uri' => $this->uri(),
            'host' => $this->host(),
            'scheme' => $this->scheme(),
            'title' => $this->title(),
            'description' => $this->description(),
            'keywords' => $this->keywords(),
            'icons' => $this->icons(),
        ];
    }
}
