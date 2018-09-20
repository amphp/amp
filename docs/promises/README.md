---
layout: docs
title: Promises
permalink: /promises/
---
A `Promise` is an object representing the eventual result of an asynchronous operation.
There are three states:

 - **Success**: The promise resolved successfully.
 - **Failure**: The promise failed.
 - **Pending**: The promise has not been resolved yet.

A successful resolution is like returning a value in synchronous code while failing a promise is like throwing an exception.

Promises are the basic unit of concurrency in asynchronous applications.
In Amp they implement the `Amp\Promise` interface.
These objects should be thought of as placeholders for values or tasks that might not be complete immediately.


Another way to approach asynchronous APIs is using callbacks that are passed when the operation is started.

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

 - Passing callbacks and doing further actions in them that depend on the result of the first action gets messy really quickly.
 - An explicit callback is required as input parameter to the function, and the return value is simply unused. There's no way to use this API without involving a callback.

That's where promises come into play.
They're simple placeholders that are returned and allow a callback (or several callbacks) to be registered.

```php
doSomething()->onResolve(function ($error, $value) {
    if ($error) {
        /* ... */
    } else {
        /* ... */
    }
});
```

This doesn't seem a lot better at first sight, we have just moved the callback.
But in fact this enabled a lot.
We can now write helper functions like [`Amp\Promise\all()`](https://amphp.org/amp/promises/combinators#all) which subscribe to several of those placeholders and combine them. We don't have to write any complicated code to combine the results of several callbacks.

But the most important improvement of promises is that they allow writing [coroutines](https://amphp.org/amp/coroutines/), which completely eliminate the need for _any_ callbacks.

Coroutines make use of PHP's generators.
Every time a promise is `yield`ed, the coroutine subscribes to the promise and automatically continues it once the promise resolved.
On successful resolution the coroutine will send the resolution value into the generator using [`Generator::send()`](https://secure.php.net/generator.send).
On failure it will throw the exception into the generator using [`Generator::throw()`](https://secure.php.net/generator.throw).
This allows writing asynchronous code almost like synchronous code.

{:.note}
> Amp's `Promise` interface **does not** conform to the "Thenables" abstraction common in JavaScript promise implementations. Chaining `.then()` calls is a suboptimal method for avoiding callback hell in a world with generator coroutines. Instead, Amp utilizes PHP generators as described above.
>
> However, as ReactPHP is another wide-spread implementation, we also accept any `React\Promise\PromiseInterface` where we accept instances of `Amp\Promise`. In case of custom implementations not implementing `React\Promise\PromiseInterface`, `Amp\Promise\adapt()` can be used to adapt any object having a `then` or `done` method.

## Promise Consumption

```php
interface Promise {
    public function onResolve(callable $onResolve);
}
```

In its simplest form the `Amp\Promise` aggregates callbacks for dealing with results once they eventually resolve. While most code will not interact with this API directly thanks to [coroutines](../coroutines/), let's take a quick look at the one simple API method exposed on `Amp\Promise` implementations:

| Parameter    | Callback Signature                         |
| ------------ | ------------------------------------------ |
| `$onResolve` | `function ($error = null, $result = null)` |

`Amp\Promise::onResolve()` accepts an error-first callback. This callback is responsible for reacting to the eventual result represented by the promise placeholder. For example:

```php
<?php

$promise = someFunctionThatReturnsAPromise();
$promise->onResolve(function (Throwable $error = null, $result = null) {
    if ($error) {
        printf(
            "Something went wrong:\n%s\n",
            $error->getMessage()
        );
    } else {
        printf(
            "Hurray! Our result is:\n%s\n",
            print_r($result, true)
        );
    }
});
```

Those familiar with JavaScript code generally reflect that the above interface quickly devolves into ["callback hell"](http://callbackhell.com/), and they're correct. We will shortly see how to avoid this problem in the [coroutines](../coroutines/README.md) section.

## Promise Creation

Promises can be created in several different ways. Most code will use [`Amp\call()`](https://amphp.org/amp/coroutines/helpers#call) which takes a function and runs it as coroutine if it returns a `Generator`.

### Success and Failure

Sometimes values are immediately available. This might be due to them being cached, but can also be the case if an interface mandates a promise to be returned to allow for async I/O but the specific implementation always having the result directly available. In these cases `Amp\Success` and `Amp\Failure` can be used to construct an immediately resolved promise. `Amp\Success` accepts a resolution value. `Amp\Failure` accepts an exception as failure reason.

### Deferred

{:.note}
> The `Deferred` API described below is an advanced API that many applications probably don't need. Use [`Amp\call()`](https://amphp.org/amp/coroutines/helpers#call) or [promise combinators](https://amphp.org/amp/promises/combinators) instead where possible.

`Amp\Deferred` is the abstraction responsible for resolving future values once they become available. A library that resolves values asynchronously creates an `Amp\Deferred` and uses it to return an `Amp\Promise` to API consumers. Once the async library determines that the value is ready it resolves the promise held by the API consumer using methods on the linked promisor.

```php
final class Deferred
{
    public function promise(): Promise;
    public function resolve($result = null);
    public function fail(Throwable $error);
}
```

#### `promise()`

Returns the corresponding `Promise` instance. `Deferred` and `Promise` are separated, so the consumer of the promise can't fulfill it. You should always return `$deferred->promise()` to API consumers. If you're passing `Deferred` objects around, you're probably doing something wrong.

#### `resolve()`

Resolves the promise with the first parameter as value, otherwise `null`. If a `Amp\Promise` is passed, the resolution will wait until the passed promise has been resolved. Invokes all registered `Promise::onResolve()` callbacks.

#### `fail()`

Makes the promise fail. Invokes all registered `Promise::onResolve()` callbacks with the passed `Throwable` as `$error` argument.

Here's a simple example of an async value producer `asyncMultiply()` creating a deferred and returning the associated promise to its API consumer.

```php
<?php // Example async producer using promisor

use Amp\Loop;

function asyncMultiply($x, $y)
{
    // Create a new promisor
    $deferred = new Amp\Deferred;

    // Resolve the async result one second from now
    Loop::delay($msDelay = 1000, function () use ($deferred, $x, $y) {
        $deferred->resolve($x * $y);
    });

    return $deferred->promise();
}

$promise = asyncMultiply(6, 7);
$result = Amp\Promise\wait($promise);

var_dump($result); // int(42)
```
