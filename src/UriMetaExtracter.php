<?php

namespace Chuoke\UriMeta;

use Exception;
use DiDom\Document;
use League\Uri\Uri;
use HeadlessChromium\BrowserFactory;

class UriMetaExtracter
{
    protected array $config;

    /** @var \League\Uri\Uri */
    protected $uri;

    /** @var \DiDom\Document */
    protected $document;

    public function __construct(array $config = null)
    {
        $this->setConfig($config ?: []);
    }

    /**
     * Merge the given configuration with the existing configuration.
     *
     * @param  array  $config
     * @return $this
     */
    public function setConfig(array $config)
    {
        $this->config = array_merge(
            require __DIR__ . '/../config/urimeta.php',
            $config
        );

        return $this;
    }

    /**
     * @param  string  $uri
     * @return \Chuoke\UriMeta\UriMeta
     * @throws Exception
     */
    public function extract(string $uri)
    {
        $this->uri = Uri::createFromString($uri);

        if (!$this->isHttp() || $this->isShouldSkip()) {
            return $this->useDefault();
        }

        return $this->extractMeta();
    }

    protected function isHttp(): bool
    {
        return stripos($this->uri->getScheme(), 'http') === 0;
    }

    protected function isShouldSkip(): bool
    {
        $host = $this->uri->getHost();

        foreach ($this->config['skips'] ?? [] as $possible) {
            if (\str_starts_with($host, $possible)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Chuoke\UriMeta\UriMeta
     */
    public function useDefault()
    {
        $uriMeta = new UriMeta($this->uri);

        $uriMeta->titles = [$this->defaultTitle()];
        $uriMeta->descriptions = [(string) $this->uri];
        $uriMeta->keywords = '';
        $uriMeta->icons = [];

        return $uriMeta;
    }

    /**
     * @return \Chuoke\UriMeta\UriMeta
     */
    protected function extractMeta()
    {
        $this->makeDocument();

        $uriMeta = new UriMeta($this->uri);

        $uriMeta->titles = $this->extractTitle();
        $uriMeta->descriptions = $this->extractDescription();
        $uriMeta->keywords = $this->extractKeywords();
        $uriMeta->icons = $this->extractIcons();

        return $uriMeta;
    }

    public function defaultTitle()
    {
        return implode(
            ':',
            array_filter([
                $this->uri->getHost(),
                $this->uri->getPort()
            ])
        );
    }

    /**
     * 提取meta标签值
     *
     * @param  array  $possibles
     * @param  boolean  $takeAll
     * @return array
     */
    protected function extractMetaValue(array $possibles, $takeAll = false)
    {
        if (!($headEle = $this->document->first('head'))) {
            return [];
        }

        $values = [];

        foreach ($possibles as $possible => $attr) {
            if (!($possibleEle = $headEle->first($possible))) {
                continue;
            }

            $value = trim(
                strcasecmp($attr, 'text') === 0
                    ? $possibleEle->text()
                    : $possibleEle->getAttribute($attr)
            );

            if ($value) {
                $values[] = $value;
            }

            if (!empty($values) && !$takeAll) {
                break;
            }
        }

        return array_unique($values);
    }

    protected function extractTitle()
    {
        $possibles = $this->config['possibles']['sub_title'];

        if ($this->isHostUri()) {
            $possibles = array_merge($this->config['possibles']['title'], $possibles);
        }

        return $this->extractMetaValue($possibles, true);
    }

    protected function extractDescription()
    {
        $possibles = $this->config['possibles']['description'];

        return $this->extractMetaValue($possibles, true);
    }

    protected function extractKeywords()
    {
        $possibles = $this->config['possibles']['keywords'];

        return $this->extractMetaValue($possibles, false)[0] ?? '';
    }

    protected function extractIcons()
    {
        $possibles = $this->config['possibles']['icon'];

        $icons = $this->extractMetaValue($possibles, true);

        $icons[] = '/favicon.ico';

        $results = [];

        foreach (array_unique($icons) as $icon) {
            $results[] = $this->fulfillSubUrl($icon);
        }

        return array_unique($results);
    }

    public function fulfillSubUrl($sub)
    {
        if ($this->startsWith($sub, ['https://', 'http://'])) {
            return $sub;
        } elseif ($this->startsWith($sub, '//')) {
            return implode(':', [$this->uri->getScheme() ?: 'http', $sub]);
        } elseif ($this->startsWith($sub, '/')) {
            return $this->hostUri() . $sub;
        }

        return implode('/', array_filter([
            $this->hostUri(),
            trim($this->uri->getPath(), '/'),
            $sub,
        ]));
    }

    protected function isHostUri(): bool
    {
        return !$this->uri->getPath();
    }

    public function hostUri(): string
    {
        return implode('', [
            $this->uri->getScheme() ?: 'http',
            '://',
            $this->uri->getHost(),
        ]);
    }

    protected function makeDocument()
    {
        if (strpos($this->uri->getScheme(), 'http') !== 0) {
            throw new Exception('Non-web link');
        }

        $html = $this->getHtmlbyChrome();

        return $this->document = new Document($html, false, 'UTF-8');
    }

    public function getHtmlbyChrome()
    {
        try {
            $browserFactory = new BrowserFactory();

            // starts headless chrome
            $browser = $browserFactory->createBrowser();

            // creates a new page and navigate to an url
            $page = $browser->createPage();

            if ($userAgent = $this->userAgent()) {
                $page->setUserAgent($userAgent);
            }

            $page->navigate((string) $this->uri)->waitForNavigation();

            return $page->getHtml();
        } finally {
            isset($browser) && $browser && $browser->close();
        }
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string  $haystack
     * @param  string|string[]  $needles
     * @return bool
     */
    protected function startsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if (str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function userAgent()
    {
        return $this->config['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36';
    }
}
