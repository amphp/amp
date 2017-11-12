<a href="https://amphp.org/">
  <img src="https://github.com/amphp/logo/blob/master/repos/amp-logo-with-margin.png?raw=true" width="250" align="right" alt="Amp Logo">
</a>

<a href="https://amphp.org/"><img alt="Amp" src="https://github.com/amphp/logo/blob/master/repos/amp-text.png?raw=true" width="100" valign="middle"></a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="https://travis-ci.org/amphp/amp"><img src="https://img.shields.io/travis/amphp/amp/master.svg?style=flat-square" valign="middle"></a> <a href="https://coveralls.io/github/amphp/amp?branch=master"><img src="https://img.shields.io/coveralls/amphp/amp/master.svg?style=flat-square" valign="middle"></a> <a href="blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" valign="middle"></a>

Amp is a non-blocking concurrency framework for PHP. It provides an event loop, promises and streams as a base for asynchronous programming.

Promises in combination with generators are used to build coroutines, which allow writing asynchronous code just like synchronous code, without any callbacks.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/amp
```

## Documentation

Documentation can be found on [amphp.org](https://amphp.org/) as well as in the [`./docs`](./docs) directory.

## Requirements

- PHP 7.0+

##### Optional Extension Backends

Extensions are only needed if your app necessitates a high numbers of concurrent socket connections.

- [ev](https://pecl.php.net/package/ev)
- [event](https://pecl.php.net/package/event)
- [php-uv](https://github.com/bwoebi/php-uv) (experimental fork)

## Examples

This simple example uses our [Artax](https://amphp.org/artax/) HTTP client to fetch multiple HTTP resources concurrently.

```php
<?php

use Amp\Artax\Response;
use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    $uris = [
        "https://google.com/",
        "https://github.com/",
        "https://stackoverflow.com/",
    ];

    $client = new Amp\Artax\DefaultClient;
    $client->setOption(Amp\Artax\Client::OP_DISCARD_BODY, true);

    try {
        foreach ($uris as $uri) {
            $promises[$uri] = $client->request($uri);
        }

        $responses = yield $promises;

        foreach ($responses as $uri => $response) {
            print $uri . " - " . $response->getStatus() . $response->getReason() . PHP_EOL;
        }
    } catch (Amp\Artax\HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The Client::request() method itself will never throw directly, but returns a promise.
        print $error->getMessage() . PHP_EOL;
    }
});
```

Further examples can be found in the [`./examples`](./examples) directory of this repository as well as in the `./examples` directory of [our other libraries](https://github.com/amphp?utf8=%E2%9C%93&q=&type=public&language=php)

## Versioning

`amphp/amp` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

| Version | Bug Fixes Until | Security Fixes Until |
| ------- | --------------- | -------------------- |
| 2.x     | TBA             | TBA                  |
| 1.x     | 2017-12-31      | 2018-12-31           |

## Compatible Packages

Compatible packages should use the [`amphp`](https://github.com/search?utf8=%E2%9C%93&q=topic%3Aamphp) topic on GitHub.

## Security

If you discover any security related issues, please email [`bobwei9@hotmail.com`](mailto:bobwei9@hotmail.com) or [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
