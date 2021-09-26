<?php

namespace Chuoke\UriMeta\Tests;

use PHPUnit\Framework\TestCase;

class ShouldSkipTest extends TestCase
{
    /** @test */
    public function can_use()
    {
        $extracter = new \Chuoke\UriMeta\UriMetaExtracter();

        $uriMeta = $extracter->extract('http://127.0.0.1');

        $this->assertEquals($uriMeta->title(), '127.0.0.1');
    }
}
