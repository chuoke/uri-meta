<?php

namespace Chuoke\UriMeta\Tests;

use Chuoke\UriMeta\UriMetaExtracter;
use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    /** @test */
    public function can_use()
    {
        $extracter = new UriMetaExtracter();


        $uriMeta = $extracter->extract('https://www.baidu.com/');

        $this->assertEquals('百度一下，你就知道', $uriMeta->title());


        $uriMeta = $extracter->extract('https://www.php.net/');

        $this->assertEquals('PHP: Hypertext Preprocessor', $uriMeta->title());


        $uriMeta = $extracter->extract('https://github.com/php/php-src');

        $this->assertEquals('GitHub - php/php-src: The PHP Interpreter', $uriMeta->title());
    }
}
