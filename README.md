# amp

[![Build Status](https://img.shields.io/travis/amphp/amp/master.svg?style=flat-square)](https://travis-ci.org/amphp/amp)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/amp/master.svg?style=flat-square)](https://coveralls.io/github/amphp/amp?branch=master)
![Stable v1](https://img.shields.io/badge/stable-v1-green.svg?style=flat-square)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`amphp/amp` is a non-blocking concurrency framework for PHP applications.

Learn more about `amphp/amp` in [the guide](http://amphp.org/docs/amp/).

**Required PHP Version**

- PHP 5.5+

**Optional Extension Backends**

Extensions are only needed if your app necessitates high numbers of concurrent socket connections.

- [ev](https://pecl.php.net/package/ev)
- [libevent](https://pecl.php.net/package/libevent)
- [php-uv](https://github.com/bwoebi/php-uv) (experimental fork, requires PHP7)

**Installation**

```bash
$ composer require amphp/amp
```
