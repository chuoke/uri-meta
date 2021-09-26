<?php

namespace Chuoke\UriMeta;

use Chuoke\UriMeta\Drivers\Browsershot;
use Chuoke\UriMeta\Drivers\ChromePhp;
use DiDom\Document;
use Exception;
use League\Uri\Uri;

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

    protected function setUri($uri)
    {
        if (! $uri instanceof Uri) {
            $uri = Uri::createFromString($uri);
        }

        $this->uri = $uri;

        return $this;
    }

    /**
     * @param  string  $uri
     * @return \Chuoke\UriMeta\UriMeta
     * @throws Exception
     */
    public function extract(string $uri): UriMeta
    {
        $this->setUri($uri);

        if (! $this->isHttp() || $this->isShouldSkip()) {
            return $this->useDefault();
        }

        return $this->fromHtml($this->getHtml(), $this->uri);
    }

    /**
     * @param  string $html
     * @param  string $uri  It is needed to complete links, such as icon links.
     * @return UriMeta
     */
    public function fromHtml(string $html, $uri): UriMeta
    {
        $this->setUri($uri);

        $this->makeDocument($html);

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
    public function useDefault(): UriMeta
    {
        $uriMeta = new UriMeta($this->uri, [
            'title' => $this->defaultTitle(),
            'description' => '',
            'keywords' => [],
            'icons' => [],
        ]);

        return $uriMeta;
    }

    protected function extractMeta(): UriMeta
    {
        $meta = [
            'title' => $this->extractTitle(),
            'description' => $this->extractDescription(),
            'keywords' => $this->extractKeywords(),
            'icons' => $this->extractIcons(),
        ];

        if ($meta['title'] && $this->isHostUri()) {
            $slogan = $this->extractSlogan($meta['title']);

            if ($slogan['slogan']) {
                $meta['slogan'] = $slogan['slogan'];
                $meta['title'] = $slogan['title'];
            }
        }

        if ($og = $this->extractOg()) {
            $meta['og'] = $og;
        }

        if ($twitter = $this->extractTwitter()) {
            $meta['twitter'] = $twitter;
        }

        return new UriMeta($this->uri, $meta);
    }

    public function defaultTitle(): string
    {
        return implode(
            ':',
            array_filter([
                $this->uri->getHost(),
                $this->uri->getPort(),
            ])
        );
    }

    protected function extractMetaValue(array $metaTags, bool $takeAll = false): string|array
    {
        if (! ($headEle = $this->document->first('head'))) {
            return [];
        }

        $values = [];

        foreach ($metaTags as $possible => $attr) {
            if (! ($possibleEle = $headEle->first($possible))) {
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

            if (! empty($values) && ! $takeAll) {
                break;
            }
        }

        $values = array_unique($values);

        return $takeAll ? $values : reset($values);
    }

    protected function extractTitle(): string
    {
        return $this->extractMetaValue(['title' => 'text']);
    }

    protected function extractDescription(): string
    {
        return $this->extractMetaValue([
            'meta[name=description]' => 'content',
            'meta[name=Description]' => 'content',
        ]);
    }

    protected function extractKeywords(): array
    {
        $keywords = (string) $this->extractMetaValue([
            'meta[name=keywords]' => 'content',
            'meta[name=Keywords]' => 'content',
        ], false);

        $keywords = explode(',', str_replace(['，', '、'], ',', $keywords));

        return array_filter($keywords);
    }

    protected function extractIcons(): array
    {
        $icons = $this->extractMetaValue([
            'link[rel="apple-touch-icon"]' => 'href',
            'link[rel="shortcut icon"]' => 'href',
            'link[rel="Shortcut Icon"]' => 'href',
            'link[rel="icon"]' => 'href',
        ], true);

        if (empty($icons)) {
            $icons[] = '/favicon.ico';
        }

        $results = [];

        foreach (array_unique($icons) as $icon) {
            $results[] = $this->fulfillSubUrl($icon);
        }

        return array_unique($results);
    }

    public function fulfillSubUrl($sub): string
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

    public function extractOg(): array
    {
        $elements = $this->document->find('head > meta[property ^="og:"]');

        if (empty($elements)) {
            return [];
        }

        $results = [];

        foreach ($elements as $element) {
            $property = trim((string) $element->getAttribute('property'), 'og:');
            if (! $property) {
                continue;
            }

            $value = trim((string) $element->getAttribute('content'));
            if ($value) {
                // Remove the previous 'og:', and replace ':' with '_'.
                $results[str_replace(':', '_', substr($property, 3))] = $value;
            }
        }

        return $results;
    }

    public function extractTwitter(): array
    {
        $elements = $this->document->find('head > meta[name ^="twitter:"]');

        if (empty($elements)) {
            return [];
        }

        $results = [];

        foreach ($elements as $element) {
            $property = trim((string) $element->getAttribute('name'), 'twitter:');
            if (! $property) {
                continue;
            }

            $value = trim((string) $element->getAttribute('content'));
            if ($value) {
                // Remove the previous 'twitter:', and replace ':' with '_'.
                $results[str_replace(':', '_', substr($property, 8))] = $value;
            }
        }

        return $results;
    }

    protected function extractSlogan($title = null)
    {
        $slogan = '';
        $newTitle = '';

        if (! $title || ! $this->isHostUri()) {
            return [
                'slogan' => $slogan,
                'title' => $newTitle,
            ];
        }

        // 我的工作台 - Gitee.com
        // 慕课网-程序员的梦工厂
        // 友盟+，国内领先的第三方全域数据智能服务商
        // Laravel - The PHP Framework For Web Artisans
        // 36氪_让一部分人先看到未来
        // 说唱帮 | 中文说唱文化爱好者交流平台
        // Chocolatey Software | Chocolatey - The package manager for Windows
        // 中国供应商 - 免费B2B信息发布网站，百度爱采购官方合作平台
        // 文鼎字库_文鼎字体_字体授权_Yestone邑石网_文鼎字库云字库大陆独家代理商
        foreach (['-', '_', '|', ':', '：', ',', '，',] as $separator) {
            if (mb_stripos($title, $separator) <= 0) {
                continue;
            }

            $strs = explode($separator, $title, 2);

            foreach ($strs as $str) {
                $str = trim($str);
                if (
                    mb_stripos($this->uri->getHost(), $str) !== false
                    || mb_stripos($str, mb_substr($this->uri->getHost(), 0, mb_strripos($this->uri->getHost(), '.'))) !== false
                ) {
                    continue;
                }

                if (mb_strlen($str) > mb_strlen($slogan)) {
                    $newTitle = $slogan;
                    $slogan = $str;
                }
            }

            break;
        }

        return [
            'slogan' => $slogan,
            'title' => $newTitle,
        ];
    }

    protected function isHostUri(): bool
    {
        return ! $this->uri->getPath() || $this->uri->getPath() === '/';
    }

    public function hostUri(): string
    {
        return implode('', [
            $this->uri->getScheme() ?: 'http',
            '://',
            $this->uri->getHost(),
        ]);
    }

    protected function makeDocument(string $html)
    {
        return $this->document = new Document($html, false, 'UTF-8');
    }

    public function getHtml(): string
    {
        if (strpos($this->uri->getScheme(), 'http') !== 0) {
            throw new Exception('Non-web url');
        }

        if (! isset($this->config['drivers']) || empty($this->config['drivers'])) {
            throw new Exception('There is no get html drivers.');
        }

        $html = '';

        foreach ($this->config['drivers'] as $driver => $config) {
            if (is_callable($config)) {
                $html = $config((string) $this->uri);
            } else {
                $method = 'getHtmlBy' . ucfirst($driver);
                $html = $this->{$method}((string) $this->uri, $config);
            }

            if ($html) {
                break;
            }
        }

        if (! $html) {
            throw new Exception('Can\'t get html content of the url.');
        }

        return $html;
    }

    public function getHtmlbyChromephp($uri, array $config = []): string
    {
        return (new ChromePhp($config))->getHtml($uri);
    }

    public function getHtmlbyBrowsershot($uri, array $config = []): string
    {
        return (new Browsershot($config))->getHtml($uri);
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
}
