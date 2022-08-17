<a href="https://amphp.org/">
  <img src="https://github.com/amphp/logo/blob/master/repos/amp-v3-logo-with-margin.png?raw=true" width="250" align="right" alt="Amp Logo">
</a>

<a href="https://amphp.org/"><img alt="Amp" src="https://github.com/amphp/logo/blob/master/repos/amp-text.png?raw=true" width="100" valign="middle"></a>

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
`amphp/amp` specifically provides futures and cancellations as fundamental primitives for asynchronous programming.
We're now using [Revolt](https://revolt.run/) instead of shipping an event loop implementation with `amphp/amp`.

Amp makes heavy use of fibers shipped with PHP 8.1 to write asynchronous code just like synchronous, blocking code. In
contrast to earlier versions, there's no need for generator based coroutines or callbacks. Similar to threads, each
fiber has its own call stack, but fibers are scheduled cooperatively by the event loop. Use `Amp\async()` to run things
concurrently.

<a href="blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" valign="middle"></a>

## Motivation

Traditionally, PHP follows a sequential execution model.
The PHP engine executes one line after the other in sequential order.
Often, however, programs consist of multiple independent sub-programs with can be executed concurrently.

If you query a database, you send the query and wait for the response from the database server in a blocking manner.
Once you have the response, you can start doing the next thing.
Instead of sitting there and doing nothing while waiting, we could already send the next database query, or do an HTTP call to an API.
Let's make use of the time we usually spend on waiting for I/O!

![](docs/images/sequential-vs-concurrent.png)

[Revolt](https://revolt.run/) allows such concurrent I/O operations. We keep the cognitive load low by avoiding callbacks.
Our APIs can be used like any other library, except that things _also_ work concurrently, because we use non-blocking I/O under the hood.
Run things concurrently using `Amp\async()` and await the result using `Future::await()` where and when you need it!

There have been various techniques for implementing concurrency in PHP over the years, e.g. callbacks and generators shipped in PHP 5.
These approaches suffered from the ["What color is your function"](https://journal.stuffwithstuff.com/2015/02/01/what-color-is-your-function/) problem, which we solved by shipping Fibers with PHP 8.1.
They allow for concurrency with multiple independent call stacks.

Fibers are cooperatively scheduled by the [event-loop](https://revolt.run), which is why they're also called coroutines.
It's important to understand that only one coroutine is running at any given time, all other coroutines are suspended in the meantime.

You can compare coroutines to a computer running multiple programs using a single CPU core.
Each program gets a timeslot to execute.
Coroutines, however, are not preemptive.
They don't get their fixed timeslot.
They have to voluntarily give up control to the event loop.

Any blocking I/O function blocks the entire process while waiting for I/O.
You'll want to avoid them.
If you haven't read the installation guide, have a look at the [Hello World example](https://v3.amphp.org/installation#hello-world) that demonstrates the effect of blocking functions.
The libraries provided by AMPHP avoid blocking for I/O.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/amp
```

If you use this library, it's very likely you want to schedule events using [Revolt](https://revolt.run),
which you should require separately, even if it's automatically installed as a dependency.

```bash
composer require revolt/event-loop
```

These packages provide the basic building blocks for asynchronous / concurrent applications in PHP. We offer a lot of packages
building on top of these, e.g.

- [`amphp/byte-stream`](https://github.com/amphp/byte-stream) providing a stream abstraction
- [`amphp/socket`](https://github.com/amphp/socket) providing a socket layer for UDP and TCP including TLS
- [`amphp/parallel`](https://github.com/amphp/parallel) providing parallel processing to utilize multiple CPU cores and
  offload blocking operations
- [`amphp/http-client`](https://github.com/amphp/http-client) providing an HTTP/1.1 and HTTP/2 client
- [`amphp/http-server`](https://github.com/amphp/http-server) providing an HTTP/1.1 and HTTP/2 application server
- [`amphp/mysql`](https://github.com/amphp/mysql) and [`amphp/postgres`](https://github.com/amphp/postgres) for
  non-blocking database access
- and [many more packages](https://github.com/amphp?type=source)

## Requirements

This package requires PHP 8.1+, no extensions required!

<small>

[Extensions](https://revolt.run/extensions) are only needed if your app necessitates a high numbers of concurrent socket
connections, usually this limit is configured up to 1024 file descriptors.

</small>

## Usage

### Future

### Cancellation

Every operation that supports cancellation accepts an instance of `Cancellation` as argument.
Cancellations are objects that allow registering handlers to subscribe to cancellation requests.
These objects are passed down to sub-operations or have to be handled by the operation itself.

`$cancellation->throwIfRequested()` can be used to fail the current operation with a `CancelledException` once cancellation has been requested.
While `throwIfRequested()` works well, some operations might want to subscribe with a callback instead. They can do so
using `Cancellation::subscribe()` to subscribe any cancellation requests that might happen.

The caller creates a `Cancellation` by using one of the implementations below.

> **Note:** Cancellations are advisory only. A DNS resolver might ignore cancellation requests after the query has been sent as the response has to be processed anyway and can still be cached. An HTTP client might continue a nearly finished HTTP request to reuse the connection, but might abort a chunked encoding response as it cannot know whether continuing is actually cheaper than aborting.

#### TimeoutCancellation

A `TimeoutCancellations` automatically cancels itself after the specified number of seconds.

```php
request("...", new Amp\TimeoutCancellation(30));
```

#### SignalCancellation

A `SignalCancellation` automatically cancels itself after a specified signal has been received by the current process.

```php
request("...", new Amp\SignalCancellation(SIGINT));
```

#### DeferredCancellation

A `DeferredCancellation` allows manual cancellation with the call of a method.
This is the preferred way if you need to register some custom callback somewhere instead of shipping your own implementation.
Only the caller has access to the `DeferredCancellation` and can cancel the operation using `DeferredCancellation::cancel()`.

```php
$deferredCancellation = new Amp\DeferredCancellation();

// Register some custom callback somewhere
onSomeEvent(fn () => $deferredCancellation->cancel());

request("...", $deferredCancellation->getCancellation());
```

#### NullCancellation

A `NullCancellation` will never be cancelled.
Cancellation is often optional, which is usually implemented by making the parameter nullable.
To avoid guards like `if ($cancellation)`, a `NullCancellation` can be used instead.

```php
$cancellation ??= new NullCancellationToken();
```

#### CompositeCancellation

A `CompositeCancellation` combines multiple independent cancellation objects. If any of these cancellations is cancelled, the `CompositeCancellation` itself will be cancelled.

## Versioning

`amphp/amp` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Compatible Packages

Compatible packages should use the [`amphp`](https://github.com/search?utf8=%E2%9C%93&q=topic%3Aamphp) topic on GitHub.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the
issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
