# amphp/amp

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
`amphp/amp` specifically provides futures and cancellations as fundamental primitives for asynchronous programming.
We're now using [Revolt](https://revolt.run/) instead of shipping an event loop implementation with `amphp/amp`.

Amp makes heavy use of fibers shipped with PHP 8.1 to write asynchronous code just like synchronous, blocking code. In
contrast to earlier versions, there's no need for generator based coroutines or callbacks. Similar to threads, each
fiber has its own call stack, but fibers are scheduled cooperatively by the event loop. Use `Amp\async()` to run things
concurrently.

## Motivation

Traditionally, PHP follows a sequential execution model.
The PHP engine executes one line after the other in sequential order.
Often, however, programs consist of multiple independent sub-programs which can be executed concurrently.

If you query a database, you send the query and wait for the response from the database server in a blocking manner.
Once you have the response, you can start doing the next thing.
Instead of sitting there and doing nothing while waiting, we could already send the next database query, or do an HTTP call to an API.
Let's make use of the time we usually spend on waiting for I/O!

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

This package requires PHP 8.1 or later. No extensions required!

[Extensions](https://revolt.run/extensions) are only needed if your app necessitates a high numbers of concurrent socket
connections, usually this limit is configured up to 1024 file descriptors.

## Usage

### Coroutines

Coroutines are interruptible functions. In PHP, they can be implemented using [fibers](https://wiki.php.net/rfc/fibers).

> **Note**
> Previous versions of Amp used generators for a similar purpose, but fibers can be interrupted anywhere in the call stack making previous boilerplate like `Amp\call()` unnecessary.

At any given time, only one fiber is running. When a coroutine suspends, execution of the coroutine is temporarily
interrupted, allowing other tasks to be run. Execution is resumed once a timer expires, stream operations are possible,
or any awaited `Future` completes.

Low-level suspension and resumption of coroutines is handled by Revolt's [`Suspension`](https://revolt.run/fibers) API.

```php
<?php

use Revolt\EventLoop;

require __DIR__ . '/vendor/autoload.php';

$suspension = EventLoop::getSuspension();

EventLoop::delay(5, function () use ($suspension): void {
    print '++ Executing callback created by EventLoop::delay()' . PHP_EOL;

    $suspension->resume(null);
});

print '++ Suspending to event loop...' . PHP_EOL;

$suspension->suspend();

print '++ Script end' . PHP_EOL;
```

Callbacks registered on the Revolt event-loop are automatically run as coroutines. It is safe to suspend within those
callbacks. Apart from the event-loop API, `Amp\async()` can be used to start a coroutine (that is, a new fiber or an
independent call stack).

```php
<?php

require __DIR__ . '/vendor/autoload.php';

Amp\async(function () {
    print '++ Executing callback passed to async()' . PHP_EOL;

    Amp\delay(3);

    print '++ Finished callback passed to async()' . PHP_EOL;
});

print '++ Suspending to event loop...' . PHP_EOL;
Amp\delay(5);

print '++ Script end' . PHP_EOL;
```

### Future

A `Future` is an object representing the eventual result of an asynchronous operation. Such placeholders are also
called a "promise" in other frameworks or languages such as JavaScript. We chose to not use the "promise" name since a
`Future` does not have a `then` method, which is typical of most promise implementations. Futures are primarily designed
to be awaited in coroutines, though `Future` also has methods which act upon the result, returning another future.

A future may be in one of three states:

- **Completed**: The future has been completed successfully.
- **Errored**: The future failed with an exception.
- **Pending**: The future is still pending.

A successfully completed future is analog to a return value, while an errored future is analog to throwing an exception.

One way to approach asynchronous APIs is using callbacks that are passed when the operation is started and called once it completes:

```php
doSomething(function ($error, $value) {
    if ($error) {
        /* ... */
    } else {
        /* ... */
    }
});
```

The callback approach has several drawbacks.

- Passing callbacks and doing further actions in them that depend on the result of the first action gets messy really
  quickly.
- An explicit callback is required as input parameter to the function, and the return value is simply unused. There's
  no way to use this API without involving a callback.

That's where futures come into play.
They're placeholders for the result that are returned like any other return value.
The caller has the choice of awaiting the result using `Future::await()` or registering one or several callbacks.

```php
try {
    $value = doSomething()->await();
} catch (...) {
    /* ... */
}
```

```php
public function await(): mixed
```

Suspends the current coroutine until the future is completed or errors. The future result is returned or an exception
thrown if the future errored.

```php
/** @param Closure(mixed $value): mixed $map */
public function map(Closure $map): Future
```

Attaches a callback which is invoked if the future completes successfully, passing the future result as an argument.
Another future is returned, which either completes with the return value of the callback, or errors if the callback
throws an exception.

```php
/** @param Closure(Throwable $exception): mixed $catch */
public function catch(Closure $catch): Future
```

Attaches a callback which is invoked if the future errors, passing the exception as the callback parameter.
Another future is returned, which either completes with the return value of the callback, or errors if the callback
throws an exception.

```php
/** @param Closure(): void $finally */
public function finally(Closure $finally): Future
```

Attaches a callback which is always invoked, whether the future completes or errors.
Another future is returned, which either completes with same value as the future, or errors if the callback
throws an exception.

#### Combinators

In concurrent applications, there will be multiple futures, where you might want to await them all or just the first one.

##### await

`Amp\Future\await($iterable, $cancellation)` awaits all `Future` objects of an `iterable`. If one of the `Future` instances errors, the operation
will be aborted with that exception. Otherwise, the result is an array matching keys from the input `iterable` to their
completion values.

The `await()` combinator is extremely powerful because it allows you to concurrently execute many asynchronous operations
at the same time. Let's look at an example using [`amphp/http-client`](https://github.com/amphp/http-client) to
retrieve multiple HTTP resources concurrently:

```php
<?php

use Amp\Future;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;

require __DIR__ . '/vendor/autoload.php';

$httpClient = HttpClientBuilder::buildDefault();
$uris = [
    "google" => "https://www.google.com",
    "news"   => "https://news.google.com",
    "bing"   => "https://www.bing.com",
    "yahoo"  => "https://www.yahoo.com",
];

try {
    $responses = Future\await(array_map(function ($uri) use ($httpClient) {
        return Amp\async(fn () => $httpClient->request(new Request($uri, 'HEAD')));
    }, $uris));

    foreach ($responses as $key => $response) {
        printf(
            "%s | HTTP/%s %d %s\n",
            $key,
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason()
        );
    }
} catch (Exception $e) {
    // If any one of the requests fails the combo will fail
    echo $e->getMessage(), "\n";
}
```

##### awaitAnyN

`Amp\Future\awaitAnyN($count, $iterable, $cancellation)` is the same as `await()` except that it tolerates individual errors. A result is returned once
exactly `$count` instances in the `iterable` complete successfully. The return value is an array of values. The
individual keys in the component array are preserved from the `iterable` passed to the function for evaluation.

##### awaitAll

`Amp\Future\awaitAll($iterable, $cancellation)` awaits all futures and returns their results as `[$errors, $values]` array.

##### awaitFirst

`Amp\Future\awaitFirst($iterable, $cancellation)` unwraps the first completed `Future`, whether successfully completed or errored.

##### awaitAny

`Amp\Future\awaitAny($iterable, $cancellation)` unwraps the first successfully completed `Future`.

#### Future Creation

Futures can be created in several ways. Most code will use [`Amp\async()`](#Coroutines) which takes a function and runs it as coroutine in another Fiber.

Sometimes an interface mandates a `Future` to be returned, but results are immediately available, e.g. because they're cached.
In these cases `Future::complete(mixed)` and `Future::error(Throwable)` can be used to construct an immediately completed `Future`.

##### DeferredFuture

> **Note**
> The `DeferredFuture` API described below is an advanced API that many applications probably don't need.
> Use [`Amp\async()`](#Coroutines) or [combinators](#Combinators) instead where possible.

`Amp\DeferredFuture` is responsible for completing a pending `Future`.
You create a `Amp\DeferredFuture` and uses its `getFuture` method to return an `Amp\Future` to the caller.
Once result is ready, you complete the `Future` held by the caller using `complete` or `error` on the linked `DeferredFuture`.

```php
final class DeferredFuture
{
    public function getFuture(): Future
    public function complete(mixed $value = null): void
    public function error(Throwable $throwable): void
}
```

> **Warning**
> If you're passing `DeferredFuture` objects around, you're probably doing something wrong.
> They're supposed to be internal state of your operation.

> **Warning**
> You can't complete a future with another future; Use `Future::await()` before calling `DeferredFuture::complete()` in such cases.

Here's a simple example of an asynchronous value producer `asyncMultiply()` creating a `DeferredFuture` and returning the
associated `Future` to its caller.

```php
<?php // Example async producer using DeferredFuture

use Amp\Future;
use Revolt\EventLoop;

require __DIR__ . '/vendor/autoload.php';

function asyncMultiply(int $x, int $y): Future
{
    $deferred = new Amp\DeferredFuture;

    // Complete the async result one second from now
    EventLoop::delay(1, function () use ($deferred, $x, $y) {
        $deferred->complete($x * $y);
    });

    return $deferred->getFuture();
}

$future = asyncMultiply(6, 7);
$result = $future->await();

var_dump($result); // int(42)
```

### Cancellation

Every operation that supports cancellation accepts an instance of `Cancellation` as argument.
Cancellations are objects that allow registering handlers to subscribe to cancellation requests.
These objects are passed down to sub-operations or have to be handled by the operation itself.

`$cancellation->throwIfRequested()` can be used to fail the current operation with a `CancelledException` once cancellation has been requested.
While `throwIfRequested()` works well, some operations might want to subscribe with a callback instead. They can do so
using `Cancellation::subscribe()` to subscribe any cancellation requests that might happen.

The caller creates a `Cancellation` by using one of the implementations below.

> **Note**
> Cancellations are advisory only. A DNS resolver might ignore cancellation requests after the query has been sent as the response has to be processed anyway and can still be cached. An HTTP client might continue a nearly finished HTTP request to reuse the connection, but might abort a chunked encoding response as it cannot know whether continuing is actually cheaper than aborting.

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

### Utilities

Several utility functions and classes are also included in this library.

```php
function delay(
    float $timeout,
    bool $reference = true,
    ?Cancellation $cancellation = null,
): void
```

`delay` suspends the current coroutine (fiber) until the given timeout has elapsed or, if provided, the cancellation
is cancelled. Optionally, the underlying event-loop callback may be unreferenced, allowing the event-loop to exit
if no other referenced events are active.

```php
/** @param int|array<int> $signals */
function trapSignal(
    int|array $signals,
    bool $reference = true,
    ?Cancellation $cancellation = null,
): int
```

`trapSignal` suspends the current coroutine (fiber) until one of the given signals is received by the process or, if
provided, the cancellation is cancelled. Optionally, the underlying event-loop callback may be unreferenced, allowing
the event-loop to exit if no other referenced events are active. The signal number of the received signal is returned.

```php
function now(): float
```

`now` returns a high-resolution time relative to an arbitrary point in time. This function may be used to calculate
time differences independent of wall-time.

```php
/**
 * @template TClosure of \Closure
 * @param TClosure $closure
 * @return TClosure
 */
function weakClosure(Closure $closure): Closure
```

`weakClosure` wraps a given closure, returning a new `Closure` instance which maintains a weak-reference to any
`$this` object held by the closure (a weak-closure). This allows a class instance to hold a self-referencing closure
without creating a circular-reference that would prevent or delay automatic garbage collection. Invoking the returned
`Closure` after the object is destroyed will throw an instance of `Error`.

#### Interval

An `Interval` registers a callback in the event-loop which is invoked within a new coroutine every given number of
seconds until either the `Interval::disable()` method is called or the object is destroyed. If an `Interval` is
disabled, it can be re-enabled using `Interval::enable()`.

Holding an instance of `Interval` within an instance of another class is a convenient way to run a repeating timer
during the existence of that object. When the holding object is destroyed, the instance of `Interval` will also be
destroyed, cancelling the repeating timer in the event-loop. Use `weakClosure()` to avoid having a circular reference
to the holding object, which will delay garbage collection of the holding object.

```php
// Creates a callback which is invoked every 0.5s
// unless disabled or the object is destroyed.
$interval = new Interval(0.5, function (): void {
    // ...
});

// Disable the repeating timer, stopping future
// invocations until enabled again.
$interval->disable();

// Enable the repeating timer. The callback will
// not be invoked until the given timeout has elapsed.
$interval->enable();
```

## Versioning

`amphp/amp` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Compatible Packages

Compatible packages should use the [`amphp`](https://github.com/search?utf8=%E2%9C%93&q=topic%3Aamphp) topic on GitHub.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the
issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
