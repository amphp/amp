---
layout: docs
title: Promises
permalink: /promises/
---
The basic unit of concurrency in Amp applications is the `Amp\Promise`. These objects should be thought of as placeholders for values or tasks that aren't yet complete. By using placeholders we're able to reason about the results of concurrent operations as if they were already complete variables.

{:.note}
> Amp's `Promise` interface **does not** conform to the "Thenables" abstraction common in JavaScript promise implementations. Chaining `.then()` calls is a suboptimal method for avoiding callback hell in a world with generator coroutines. Instead, Amp utilizes PHP generators to "synchronize" concurrent task execution.
>
> However, as ReactPHP is another wide-spread implementation, we also accept any `React\Promise\PromiseInterface` where we accept instances of `Amp\Promise`. In case of custom implementations not implementing `React\Promise\PromiseInterface`, `Amp\Promise\adapt` can be used to adapt any object having a `then` or `done` method.

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

`Amp\Deferred` is the abstraction responsible for resolving future values once they become available. A library that resolves values asynchronously creates an `Amp\Deferred` and uses it to return an `Amp\Promise` to API consumers. Once the async library determines that the value is ready it resolves the promise held by the API consumer using methods on the linked promisor.

```php
final class Deferred {
    public function promise(): Promise;
    public function resolve($result = null);
    public function fail(Throwable $error);
}
```

#### `promise()`

Returns the corresponding `Promise` instance. `Deferred` and `Promise` are separated, so the consumer of the promise can't fulfill it. You should always return `$deferred->promise()` to API consumers. If you're passing `Deferred` objects around, you're probably doing something wrong.

{:.note}
> This separation of concerns is generally a good thing. However, creating two objects instead of one for each fundamental placeholder is a measurable performance penalty. For that reason, this separation only exists if assertions are enabled to ensure the code does what it's expected to do. `Deferred` directly implements `Promise` if assertions are disabled.

#### `resolve()`

Resolves the promise with the first parameter as value, otherwise `null`. If a `Amp\Promise` is passed, the resolution will wait until the passed promise has been resolved. Invokes all registered `Promise::onResolve()` callbacks.

#### `fail()`

Makes the promise fail. Invokes all registered `Promise::onResolve()` callbacks with the passed `Throwable` as `$error` argument.

Here's a simple example of an async value producer `asyncMultiply()` creating a deferred and returning the associated promise to its API consumer.

```php
<?php // Example async producer using promisor

use Amp\Loop;

function asyncMultiply($x, $y) {
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

### Success and Failure

Sometimes values are immediately available. This might be due to them being cached, but can also be the case if an interface mandates a promise to be returned to allow for async I/O but the specific implementation always having the result directly available. In these cases `Amp\Success` and `Amp\Failure` can be used to construct an immediately resolved promise. `Amp\Success` accepts a resolution value. `Amp\Failure` accepts an exception as failure reason.
