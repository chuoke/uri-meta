<?php

return [
    'drivers' => [
        // https://github.com/chrome-php/chrome
        'chromephp' => [
            // connect broser use executable_path or url
            'wsendpoint' => null,
            // 'browser_bin' => '/usr/bin/chromium-browser',
            'browser_bin' => null,
            'debugging_port' => 9222,
            'browser_options' => [
                'keepAlive' => true,
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'noSandbox' => true,
                // 'sendSyncDefaultTimeout' => 10000,
                // 'debugLogger' => true,
                // 'connectionDelay' => 5,
                'customFlags' => [
                    // '--no-sandbox',
                    '--enable-logging',
                    '--v1=1',
                    '--disable-gpu',
                    '--disable-software-rasterizer',
                    '--disable-dev-shm-usage',
                    '--no-first-run',
                    '--remote-debugging-address=0.0.0.0',
                    '--remote-debugging-port=9222',
                    '--remote-debugging-pipe',
                ],
            ],
            'headers' => [
                'accept-language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            ],
        ],

        // https://github.com/spatie/browsershot
        'browsershot' => [
            // connect broser use executable_path or url
            'node_binary' => null,
            'npm_binary' => null,
            'chrome_path' => null,
            'chrome_args' => [],
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ],
    ],

    /*
    | URIs that start with the following strings will not be resolved.
    */
    'skips' => [
        'localhost',
        '127',
        '172',
        '192',
    ],
];
