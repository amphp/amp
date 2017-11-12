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

## Requirements

- PHP 7.0+

##### Optional Extension Backends

Extensions are only needed if your app necessitates a high numbers of concurrent socket connections.

- [ev](https://pecl.php.net/package/ev)
- [event](https://pecl.php.net/package/event)
- [php-uv](https://github.com/bwoebi/php-uv) (experimental fork)

## Documentation

Documentation is bundled within this repository in the [`./docs`](./docs) directory.

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
