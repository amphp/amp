<a href="https://amphp.org/">
  <img src="https://github.com/amphp/logo/blob/master/repos/amp-logo-with-margin.png?raw=true" width="250" align="right" alt="Amp Logo">
</a>

<a href="https://amphp.org/"><img alt="Amp" src="https://github.com/amphp/logo/blob/master/repos/amp-text.png?raw=true" width="100" valign="middle"></a>

Amp is a set of seamlessly integrated concurrency libraries for PHP based on [Revolt](https://revolt.run/). This package
provides futures and cancellations as a base for asynchronous programming.

Amp makes heavy use of fibers shipped with PHP 8.1 to write asynchronous code just like synchronous, blocking code. In
contrast to earlier versions, there's no need for generator based coroutines or callbacks. Similar to threads, each
fiber has its own call stack, but fibers are scheduled cooperatively by the event loop. Use `Amp\async()` to run things
concurrently.

<a href="blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" valign="middle"></a>

## Motivation

Traditionally, PHP has a synchronous execution flow, doing one thing at a time. If you query a database, you send the
query and wait for the response from the database server in a blocking manner. Once you have the response, you can start
doing the next thing.

Instead of sitting there and doing nothing while waiting, we could already send the next database query, or do an HTTP
call to an API.

Making use of the time we usually spend on waiting for I/O can speed up the total execution time. The following diagram
shows the execution flow with dependencies between the different tasks, once executed sequentially and once
concurrently.

![](docs/images/sequential-vs-concurrent.png)

Amp allows such concurrent I/O operations while keeping the cognitive load low by avoiding callbacks. Instead, the
results of asynchronous operations can be awaited using `Future::await()` resulting in code which is structured like
traditional blocking I/O code.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/amp
```

If you use this library, it's very likely you want to schedule events using [Revolt's event loop](https://revolt.run),
which you should require separately, even if it's automatically installed as a dependency.

```bash
composer require revolt/event-loop
```

These packages provide the basic building blocks for asynchronous applications in PHP. We offer a lot of repositories
building on top of these repositories, e.g.

- [`amphp/byte-stream`](https://github.com/amphp/byte-stream) providing a stream abstraction
- [`amphp/socket`](https://github.com/amphp/socket) providing a socket layer for UDP and TCP including TLS
- [`amphp/parallel`](https://github.com/amphp/parallel) providing parallel processing to utilize multiple CPU cores and
  offload blocking operations
- [`amphp/http-client`](https://github.com/amphp/http-client) providing an HTTP/1.1 and HTTP/2 client
- [`amphp/http-server`](https://github.com/amphp/http-server) providing an HTTP/1.1 and HTTP/2 application server
- [`amphp/mysql`](https://github.com/amphp/mysql) and [`amphp/postgres`](https://github.com/amphp/postgres) for
  non-blocking database access
- and [many more packages](https://github.com/amphp?type=source)

## Documentation

Documentation can be found on [amphp.org](https://amphp.org/) as well as in the [`./docs`](./docs) directory. Each
packages has it's own `./docs` directory.

## Requirements

This package requires PHP 8.0 (with [`ext-fiber`](https://github.com/amphp/ext-fiber)), or PHP 8.1 or later. No
extensions required!

##### Optional Extensions

[Extensions](https://revolt.run/extensions) are only needed if your app necessitates a high numbers of concurrent socket
connections, usually this limit is configured up to 1024 file descriptors.

## Versioning

`amphp/amp` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Compatible Packages

Compatible packages should use the [`amphp`](https://github.com/search?utf8=%E2%9C%93&q=topic%3Aamphp) topic on GitHub.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the
issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
