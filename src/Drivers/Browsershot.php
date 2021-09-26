<?php

namespace Chuoke\UriMeta\Drivers;

use Spatie\Browsershot\Browsershot as BaseBrowsershot;

class Browsershot extends BaseBrowsershot
{
    protected array $config;

    public function __construct(array $config = [], string $url = '', bool $deviceEmulate = false)
    {
        parent::__construct($url, $deviceEmulate);

        $this->setConfig($config);
    }

    public function setConfig(array $config)
    {
        $this->config = $config;

        if ($userAgent = ($config['user_agent'] ?? null)) {
            $this->useAgent($userAgent);
        }

        if ($nodeBinary = ($config['node_binary'] ?? null)) {
            $this->setNodeBinary($nodeBinary);
        }

        if ($npmBinary = ($config['npm_binary'] ?? null)) {
            $this->setNpmBinary($npmBinary);
        }

        if ($chromePath = ($config['chrome_path'] ?? null)) {
            $this->setChromePath($chromePath);
        }

        if ($chromeArgs = ($config['chrome_args'] ?? [])) {
            $this->addChromiumArguments($chromeArgs);
        }

        return $this;
    }

    public function getHtml(string $url)
    {
        $this->setUrl($url);

        return $this->bodyHtml();
    }
}
