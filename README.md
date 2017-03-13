# amp

[![Build Status](https://img.shields.io/travis/amphp/amp/master.svg?style=flat-square)](https://travis-ci.org/amphp/amp)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/amp/master.svg?style=flat-square)](https://coveralls.io/github/amphp/amp?branch=master)
![Unstable v2](https://img.shields.io/badge/unstable-v2-green.svg?style=flat-square)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`amphp/amp` is a non-blocking concurrency framework for PHP. It provides an event loop, promises and streams as a base for asynchronous programming.

 Promises in combination with generators are used to build coroutines, which allow writing asynchronous code just like synchronous code, without any callbacks.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/amp ^2@dev
```

## Requirements

- PHP 7.0+

##### Optional Extension Backends

Extensions are only needed if your app necessitates a high numbers of concurrent socket connections.

- [ev](https://pecl.php.net/package/ev)
- [libevent](https://pecl.php.net/package/libevent)
- [php-uv](https://github.com/bwoebi/php-uv) (experimental fork)

## Documentation

Documentation is bundled within this repository in the [`./docs`](./docs) directory.

## Versioning

`amphp/amp` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Compatible Packages

Compatible packages should use the [`amphp`](https://github.com/search?utf8=%E2%9C%93&q=topic%3Aamphp) topic on GitHub.

## Security

If you discover any security related issues, please email [`bobwei9@hotmail.com`](mailto:bobwei9@hotmail.com) or [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [LICENSE](./LICENSE) for more information.
