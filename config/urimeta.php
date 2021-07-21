<?php

return [

    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',

    'possibles' => [
        'title' => [
            'meta[name=apple-mobile-web-app-title]' => 'content',
            'meta[name=og:site_name]' => 'content',
            'meta[name=application-name]' => 'content',
        ],
        'sub_title' => [
            'meta[name=twitter:title]' => 'content',
            'meta[property=og:title]' => 'content',
            'meta[name=title]' => 'content',
            'title' => 'text',
        ],
        'description' => [
            'meta[name=twitter:description]' => 'content',
            'meta[property=og:description]' => 'content',
            'meta[name=description]' => 'content',
            'meta[name=Description]' => 'content',
        ],
        'keywords' => [
            'meta[name=keywords]' => 'content',
            'meta[name=Keywords]' => 'content',
        ],
        'icon' => [
            'link[rel="apple-touch-icon"]' => 'href',
            'link[rel="icon"]' => 'href',
            'link[rel="shortcut icon"]' => 'href',
            'link[rel="Shortcut Icon"]' => 'href',
            'link[rel="mask-icon"]' => 'href',
        ],
    ],

    /*
    | URIs that start with the following strings will not be resolved.
    */
    'skips' => [
        'localhost',
        '127',
        '192',
    ],
];
