---
layout: "docs"
title: "Futures"
permalink: "/futures/"
---
A `Future` is an object representing the eventual result of an asynchronous operation. There are three states:

- **Completed successfully**: The future has been completed successfully.
- **Errored**: The future failed with an exception.
- **Pending**: The future is still pending.

A successfully completed future is analog to a return value, while an errored future is analog to throwing an exception.

Futures are the basic unit of concurrency in asynchronous applications. These objects should be thought of as
placeholders for values or tasks that might not be complete immediately.

Another way to approach asynchronous APIs is using callbacks that are passed when the operation is started and called
once it completes:

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
- An explicit callback is required as input parameter to the function, and the return value is simply unused. There's no
  way to use this API without involving a callback.

That's where futures come into play. They're simple placeholders that are returned and allow a callback (or several
callbacks) to be registered.

```php
try {
    $value = doSomething()->await();
} catch (...) {
    /* ... */
}
```

We can now write helper functions like `Amp\Future\await()` which
subscribe to several of those placeholders and combine them. We don't have to write any complicated code to combine the
results of several operations.

## Future Creation

Futures can be created in several ways. Most code will use [`Amp\async()`](https://amphp.org/amp/coroutines/)
which takes a function and runs it as coroutine.

### Immediately Available Results

Sometimes results are immediately available. This might be due to them being cached, but can also be the case if an
interface mandates a `Future` to be returned. In these cases `Future::complete(mixed)` and `Future::error(Throwable)`
can be used to construct an immediately completed `Future`.

### DeferredFuture

{:.note}
> The `DeferredFuture` API described below is an advanced API that many applications probably don't need.
> Use [`Amp\async()`](https://amphp.org/amp/coroutines/) or [combinators](https://amphp.org/amp/futures/combinators) instead where possible.

`Amp\DeferredFuture` is the abstraction responsible for resolving future values once they become available. A library
that resolves values asynchronously creates an `Amp\DeferredFuture` and uses it to return an `Amp\Future` to API
consumers. Once the library determines that the value is ready, it resolves the `Future` held by the API consumer using
methods on the linked `DeferredFuture`.

```php
final class DeferredFuture
{
    public function getFuture(): Future;
    public function complete(mixed $value = null);
    public function error(Throwable $throwable);
}
```

#### `getFuture()`

Returns the corresponding `Future` instance. `DeferredFuture` and `Future` are separated, so the consumer of
the `Future` can't complete it. You should always return `Future` to API consumers. If you're passing `DeferredFuture`
objects around, you're probably doing something wrong.

#### `complete()`

Completes the future with the first parameter as value, otherwise `null`. Instances of `Amp\Future` are not supported;
Use `Future::await()` before calling `DeferredFuture::complete()` in such cases.

#### `error()`

Makes the future fail.

#### Future Example

Here's a simple example of an async value producer `asyncMultiply()` creating a `DeferredFuture` and returning the
associated `Future` to its API consumer.

```php
<?php // Example async producer using DeferredFuture

use Revolt\EventLoop;

function asyncMultiply(int $x, int $y): Future
{
    $deferred = new Amp\DeferredFuture;

    // Resolve the async result one second from now
    EventLoop::delay(1, function () use ($deferred, $x, $y) {
        $deferred->resolve($x * $y);
    });

    return $deferred->getFuture();
}

$future = asyncMultiply(6, 7);
$result = $future->await();

var_dump($result); // int(42)
```
