# Get URI META

[![Latest Version on Packagist](https://img.shields.io/packagist/v/chuoke/uri-meta.svg?style=flat-square)](https://packagist.org/packages/chuoke/uri-meta)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/chuoke/uri-meta/run-tests?label=tests)](https://github.com/chuoke/uri-meta/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/chuoke/uri-meta/Check%20&%20fix%20styling?label=code%20style)](https://github.com/chuoke/uri-meta/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/chuoke/uri-meta.svg?style=flat-square)](https://packagist.org/packages/chuoke/uri-meta)

---

This package can be used as to extract the META information of the URI.

## Installation

You can install the package via composer:

```bash
composer require chuoke/uri-meta
```

## Usage

```php
    $extracter = new UriMetaExtracter();

    $uriMeta = $extracter->extract('https://www.php.net/');

    echo $uriMeta->title(); // PHP: Hypertext Preprocessor

    print_r($uriMeta->toArray());
    // Array
    // (
    //     [uri] => https://www.php.net/
    //     [host] => www.php.net
    //     [scheme] => https
    //     [title] => PHP: Hypertext Preprocessor
    //     [description] => https://www.php.net/
    //     [keywords] =>
    //     [icons] => Array
    //         (
    //             [0] => https://www.php.net/favicon.ico
    //         )

    // )

    $uriMeta = $extracter->extract('https://github.com/php/php-src');

    print_r($uriMeta->toArray());
    // Array
    // (
    //     [uri] => https://github.com/php/php-src
    //     [host] => github.com
    //     [scheme] => https
    //     [title] => GitHub - php/php-src: The PHP Interpreter
    //     [description] => The PHP Interpreter. Contribute to php/php-src development by creating an account on GitHub.
    //     [keywords] =>
    //     [icons] => Array
    //         (
    //             [0] => https://github.githubassets.com/favicons/favicon.svg
    //             [1] => https://github.githubassets.com/pinned-octocat.svg
    //             [2] => https://github.com/favicon.ico
    //         )
    // )
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
