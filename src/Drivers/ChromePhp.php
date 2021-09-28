<?php

namespace Chuoke\UriMeta\Drivers;

use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Page;
use Illuminate\Http\Client\Factory;
use Throwable;

class ChromePhp
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getHtml($url): string
    {
        $browser = null;
        $page = null;

        try {
            // starts headless chrome
            $browser = $this->makeBrower();

            // creates a new page and navigate to an url
            $page = $browser->createPage();

            $this->settingPage($page);

            $page->navigate((string) $url)->waitForNavigation();

            $html = $page->getHtml();

            return $html;
        } finally {
            if ($page) {
                $page->close();
            }

            if ($browser && $browser->getConnection()->isConnected()) {
                $browser->getConnection()->disconnect();
            }
        }
    }

    protected function makeBrower(): Browser
    {
        if ($wsendpoint = $this->getWsendpoint()) {
            $connection = new Connection($wsendpoint);
            $connection->connect();

            return new Browser($connection);
        }

        $browserFactory = new BrowserFactory($this->config['browser_bin'] ?? null);

        return $browserFactory->createBrowser($this->getBrowserOptions());
    }

    protected function getWsendpoint(): string|null
    {
        if (isset($this->config['wsendpoint']) && !empty($this->config['wsendpoint'])) {
            return $this->config['wsendpoint'];
        }

        $port = $this->debuggingPort();

        try {
            $response = (new Factory())->get("http://127.0.0.1:${port}/json/version");

            if ($response->ok()) {
                return $response->json('webSocketDebuggerUrl');
            }
        } catch (Throwable $e) {
            //
        }

        return null;
    }

    protected function debuggingPort()
    {
        $port = $this->config['debugging_port'] ?? null;

        if (!$port) {
            foreach ($this->config['browser_options']['customFlags'] ?? [] as $op) {
                if (stripos($op, '--remote-debugging-port') === 0) {
                    $port = str_replace('--remote-debugging-port=', '', strtolower($op));
                    break;
                }
            }
        }

        return $this->config['debugging_port'] = ($port ?: '9222');
    }

    protected function getBrowserOptions()
    {
        $options = $this->config['browser_options'] ?? [];

        $debuggingPortOption = '--remote-debugging-port=' . $this->debuggingPort();
        if (!array_key_exists('customFlags', $options)) {
            $options['customFlags'] = [];
        }

        if (!in_array($debuggingPortOption, $options['customFlags'])) {
            array_push($options['customFlags'], $debuggingPortOption);
        }

        return $options;
    }

    protected function settingPage(Page $page)
    {
        if ($userAgent = ($this->config['user_agent'] ?? null)) {
            $page->setUserAgent($userAgent);
        }

        if ($headers = ($this->config['headers'] ?? null)) {
            $page->getSession()->sendMessage(new Message(
                'Network.setExtraHTTPHeaders',
                ['headers' => $headers]
            ));
        }

        return $page;
    }
}
