<?php

namespace Chuoke\UriMeta;

use Chuoke\UriMeta\Drivers\Browsershot;
use Chuoke\UriMeta\Drivers\ChromePhp;
use DiDom\Document;
use Exception;
use Illuminate\Http\Client\Factory;
use League\Uri\Uri;
use Throwable;

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
        ];

        $siteName = '';
        $themeColor = '';
        $icons = [];

        if ($this->isHostUri()) {
            if ($manifest = $this->extractManifest()) {
                $meta['manifest'] = $manifest;
                $themeColor = $manifest['theme_color'] ?? '';
                $icons = $manifest['icons'] ?? [];
            }

            $slogan = $this->extractSlogan($meta['title'], $this->uri->getHost(), $manifest['name'] ?? null);

            if ($slogan['slogan']) {
                $meta['slogan'] = $slogan['slogan'];
            }

            $siteName = $manifest['name'] ?? $slogan['site_name'];
        }

        $meta['icons'] = $icons ?: $this->extractIcons();

        if ($og = $this->extractOg()) {
            $meta['og'] = $og;
            $siteName = $og['site_name'] ?? $siteName;
        }

        if ($twitter = $this->extractTwitter()) {
            $meta['twitter'] = $twitter;
        }

        if ($siteName) {
            $meta['site_name'] = $siteName;
        }

        if ($themeColor || ($themeColor = $this->extractThemeColor())) {
            $meta['theme_color'] = $themeColor;
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

    protected function extractMetaValue(array $metaTags, bool $takeAll = false, bool $associated = false): string|array
    {
        if (! ($headEle = $this->document->first('head'))) {
            return [];
        }

        $values = [];

        foreach ($metaTags as $possible => $attr) {
            if (! ($possibleEle = $headEle->first($possible))) {
                continue;
            }

            if ($associated || is_array($attr)) {
                $values[] = $possibleEle->attributes(is_array($attr) ? $attr : [$attr]) ?: [];
            } else {
                $value = trim(
                    strcasecmp($attr, 'text') === 0
                        ? $possibleEle->text()
                        : $possibleEle->getAttribute($attr)
                );

                if ($value && ! in_array($value, $values)) {
                    $values[] = $value;
                }
            }

            if (! empty($values) && ! $takeAll) {
                break;
            }
        }

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
        /**
            // Target ios browsers.
            <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
            // Target safari on MacOS.
            <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
            // The classic favicon displayed in tabs.
            <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
            // Used by Android Chrome for the "Add to home screen" icon and settings.
            <link rel="manifest" href="/site.webmanifest">
            // Used for Safari pinned tabs.
            <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#193860">
            // Target older browsers like IE 10 and lower.
            <link rel="icon" href="/favicon.ico">
            // Used by Chrome, Firefox OS, and opera to change the browser address bar.
            <meta name="theme-color" content="#ccccc7">
            // Used by windows 8, 8.1, and 10 for the start menu tiles.
            <meta name="msapplication-TileColor" content="#00aba9">
         */

        $icons = $this->extractMetaValue([
            'link[rel="apple-touch-icon"]' => ['rel', 'type', 'sizes', 'href'],
            'link[rel="shortcut icon"]' => ['rel', 'type', 'sizes', 'href'],
            'link[rel="Shortcut Icon"]' => ['rel', 'type', 'sizes', 'href'],
            'link[rel="icon"]' => ['rel', 'type', 'sizes', 'href'],
        ], true);

        if (empty($icons)) {
            $icons[] = [
                'href' => '/favicon.ico',
            ];
        }

        $results = [];
        $exists = [];
        foreach ($icons as $icon) {
            $icon['src'] = $this->fulfillSubUrl($icon['href'], $this->uri);
            if (! in_array($icon['src'], $exists)) {
                $results[] = $icon;
                $exists[] = $icon['src'];
            }
        }

        return $results;
    }

    public function fulfillSubUrl(string $sub, Uri $baseUri): string
    {
        if ($this->startsWith($sub, ['https://', 'http://'])) {
            return $sub;
        }

        if ($this->startsWith($sub, '//')) {
            return ($baseUri->getScheme() ?: 'http') . ':' . $sub;
        }

        if ($this->startsWith($sub, '/')) {
            return ((string) $baseUri->withPath('')) . $sub;
        }

        return rtrim(((string) $baseUri->withQuery('')), '/') . '/' . $sub;
    }

    public function extractOg(): array
    {
        $elements = $this->document->find('head > meta[property ^="og:"]');

        if (empty($elements)) {
            return [];
        }

        $results = [];

        foreach ($elements as $element) {
            // Remove the previous 'og:', and replace ':' with '_'.
            if (! ($property = substr(trim((string) $element->getAttribute('property')), 3))) {
                continue;
            }

            $property = str_replace(':', '_', $property);

            if ($value = trim((string) $element->getAttribute('content'))) {
                $results[$property] =
                    in_array($property, ['image', 'image_src']) ? $this->fulfillSubUrl($value, $this->uri) : $value;
            }
        }

        return $results;
    }

    public function extractTwitter(): array
    {
        $propertyKey = 'name';
        foreach (['name', 'property'] as $p) {
            $propertyKey = $p;
            if ($elements = $this->document->find('head > meta[' . $p . ' ^="twitter:"]')) {
                break;
            }
        }

        if (! $elements) {
            return [];
        }

        $results = [];

        foreach ($elements as $element) {
            // Remove the previous 'twitter:', and replace ':' with '_'.
            if (!($property = substr(trim((string) $element->getAttribute($propertyKey)), 8))) {
                continue;
            }

            $property = str_replace(':', '_', $property);

            if ($value = trim((string) $element->getAttribute('content'))) {
                $results[$property] =
                    in_array($property, ['image', 'image_src']) ? $this->fulfillSubUrl($value, $this->uri) : $value;
            }
        }

        return $results;
    }

    protected function extractSlogan(string $title)
    {
        $slogan = '';
        $siteName = '';

        if (! $title || ! $this->isHostUri()) {
            return [
                'slogan' => $slogan,
                'site_name' => $siteName,
            ];
        }

        // GitHub: Where the world builds software · GitHub
        // 我的工作台 - Gitee.com
        // 慕课网-程序员的梦工厂
        // 友盟+，国内领先的第三方全域数据智能服务商
        // Laravel - The PHP Framework For Web Artisans
        // 36氪_让一部分人先看到未来
        // 说唱帮 | 中文说唱文化爱好者交流平台
        // Chocolatey Software | Chocolatey - The package manager for Windows
        // 中国供应商 - 免费B2B信息发布网站，百度爱采购官方合作平台
        // 文鼎字库_文鼎字体_字体授权_Yestone邑石网_文鼎字库云字库大陆独家代理商
        foreach (['·', '-', '_', '|', ':', '：', ',', '，',] as $separator) {
            if (mb_stripos($title, $separator) <= 0) {
                continue;
            }

            [$str1, $str2] = array_map(fn ($str) => trim($str), explode($separator, $title, 2));

            if (mb_strlen($str1) > mb_strlen($str2)) {
                $siteName = $str2;
                $slogan = $str1;
            } else {
                $siteName = $str1;
                $slogan = $str2;
            }

            break;
        }

        return [
            'slogan' => $slogan,
            'site_name' => $siteName,
        ];
    }

    protected function wordCount($str)
    {
        return str_word_count($str) === 0 ? mb_strlen($str) : str_word_count($str);
    }

    protected function extractManifest()
    {
        // <link rel="manifest" href="/manifest.json" crossOrigin="use-credentials">
        $manifestUrl = $this->extractMetaValue([
            'link[rel="manifest"]' => 'href',
        ]);

        if (! $manifestUrl) {
            return;
        }

        $manifestUrl = $this->fulfillSubUrl(urldecode($manifestUrl), $this->uri);

        try {
            $response = (new Factory())->get($manifestUrl);

            if ($response->ok()) {
                return $response->json();
            }
        } catch (Throwable $e) {
            //
        }

        return;
    }

    protected function extractThemeColor()
    {
        // <meta name="theme-color" content="#032541">
        return $this->extractMetaValue([
            'meta[name="theme-color"]' => 'content',
            'meta[name="msapplication-TileColor"]' => 'content',
        ]);
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
