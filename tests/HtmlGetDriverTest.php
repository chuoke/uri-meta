<?php

namespace Chuoke\UriMeta\Tests;

use Chuoke\UriMeta\UriMetaExtracter;
use PHPUnit\Framework\TestCase;

class HtmlGetDriverTest extends TestCase
{
    /** @test */
    public function can_use()
    {
        $confing = [
            'drivers' => [
                // // https://github.com/chrome-php/chrome
                'chromephp' => [
                    // connect broser use executable_path or url
                    'browser_bin' => null,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],

                // https://github.com/spatie/browsershot
                'browsershot' => [
                    // connect broser use executable_path or url
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
            ],
        ];

        $extracter = new UriMetaExtracter($confing);


        $uriMeta = $extracter->extract('https://dev.to/themesberg/20-laravel-themes-and-templates-for-your-next-project-3dc2');

        $this->assertTrue((bool) $uriMeta->og());
    }
}
