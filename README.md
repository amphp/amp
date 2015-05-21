# Amp [![Build Status](https://travis-ci.org/amphp/amp.svg?branch=master)](https://travis-ci.org/amphp/amp)

`amphp/amp` is a non-blocking concurrency framework for PHP applications. Learn more about Amp in the
[Guide](https://stackedit.io/viewer#!url=https://raw.githubusercontent.com/amphp/amp/master/guide.md).

**Dependencies**

- PHP 5.5+

Optional PHP extensions may be used to improve performance in production environments and react to process control signals:

- [php-uv](https://github.com/chobie/php-uv) extension for libuv backends

**Maintained Versions**

 - v1.0.0 (PHP 5.5+)
 - v1.1.0 (PHP 7+)

Although there are no API breaks moving from v1.0 to v1.1 the newer release *does* require PHP 7. Amp follows the [semver](http://semver.org/) semantic versioning specification. As a result, users may require any version >= 1.0.0 using the caret operator as shown below without backwards-compatibility issues. Composer will automatically retrieve the latest stable version available for your PHP environment.

**Installation**

```bash
$ composer require amphp/amp:^1
```
