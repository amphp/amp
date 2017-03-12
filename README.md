# amp

[![Build Status](https://img.shields.io/travis/amphp/amp/master.svg?style=flat-square)](https://travis-ci.org/amphp/amp)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/amp/master.svg?style=flat-square)](https://coveralls.io/github/amphp/amp?branch=master)
![Unstable v2](https://img.shields.io/badge/unstable-v2-green.svg?style=flat-square)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`amphp/amp` is a non-blocking concurrency framework for PHP. It provides an event loop, promises and streams as a base for asynchronous programming.
 
 Promises in combination with generators are used to build coroutines, which allow writing asynchronous code just like synchronous code, without any callbacks.

**Required PHP Version**

- PHP 7.0+

**Optional Extension Backends**

Extensions are only needed if your app necessitates high numbers of concurrent socket connections.

- [ev](https://pecl.php.net/package/ev)
- [libevent](https://pecl.php.net/package/libevent)
- [php-uv](https://github.com/bwoebi/php-uv) (experimental fork, requires PHP 7)

**Installation**

```bash
$ composer require amphp/amp ^2@dev
```
