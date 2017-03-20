# Managing Concurrency

The weak link when managing concurrency is humans; we simply don't think asynchronously or in parallel. Instead, we're really good at doing one thing at a time and the world around us generally fits this model. So to effectively design for concurrent processing in our code we have a couple of options:

1. Get smarter (not feasible);
2. Abstract concurrent task execution to make it feel synchronous.

## Promises

The basic unit of concurrency in Amp applications is the `Amp\Promise`. These objects should be thought of as placeholders for values or tasks that aren't yet complete. By using placeholders we're able to reason about the results of concurrent operations as if they were already complete variables.

> **NOTE**
>
> Amp's `Promise` interface **does not** conform to the "Thenables" abstraction common in JavaScript promise implementations. Chaining `.then()` calls is a suboptimal method for avoiding callback hell in a world with generator coroutines. Instead, Amp utilizes PHP generators to "synchronize" concurrent task execution.
>
> However, as ReactPHP is another wide-spread implementation, we also accept any `React\Promise\PromiseInterface` where we accept instances of `Amp\Promise`. In case of custom implementations not implementing `React\Promise\PromiseInterface`, `Amp\adapt` can be used to adapt any object having a `then` or `done` method.

### The Promise API

```php
interface Promise {
    public function when(callable $onResolve);
}
```

In its simplest form the `Amp\Promise` aggregates callbacks for dealing with computational results once they eventually resolve. While most code will not interact with this API directly thanks to the magic of [Generators](#generators), let's take a quick look at the one simple API method exposed on `Amp\Promise` implementations:


| Method    | Callback Signature                                        |
| --------- | ----------------------------------------------------------|
| when      | `function ($error = null, $result = null)` |

`Amp\Promise::when()` accepts an error-first callback. This callback is responsible for reacting to the eventual result of the computation represented by the promise placeholder. For example:

```php
<?php

$promise = someFunctionThatReturnsAPromise();
$promise->when(function (Throwable $error = null, $result = null) {
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

Those familiar with JavaScript code generally reflect that the above interface quickly devolves into ["callback hell"](http://callbackhell.com/), and they're correct. We will shortly see how to avoid this problem in the [Generators](#generators) section.

## Deferred

`Amp\Deferred` is the abstraction responsible for resolving future values once they become available. A library that resolves values asynchronously creates an `Amp\Deferred` and uses it to return an `Amp\Promise` to API consumers. Once the async library determines that the value is ready it resolves the promise held by the API consumer using methods on the linked promisor.

### The Deferred API

```php
final class Deferred {
    public function promise(): Promise;
    public function resolve($result = null);
    public function fail($error);
}
```

#### `promise()`

Returns the corresponding `Promise` instance. `Deferred` and `Promise` are separated, so the consumer of the promise can't fulfill it.

#### `resolve()`

Resolves the promise with the first parameter as value, otherwise `null`. If a `Amp\Promise` is passed, the resolution will wait until the passed promise has been resolved. Invokes all registered `Promise::when()` callbacks.

#### `fail()`

Makes the promise fail. Invokes all registered `Promise::when()` callbacks with the passed `Throwable` as `$error` argument.

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
$result = Amp\wait($promise);
var_dump($result); // int(42)
```

## Combinators

### `map()`

Maps eventual promise results using the specified callable.

### `filter()`

Filters eventual promise results using the specified callable.

If the functor returns a truthy value the resolved promise result is retained, otherwise it is
discarded. Array keys are retained for any results not filtered out by the functor.


## Generators

The addition of generators in PHP 5.5 trivializes synchronization and error handling in async contexts. The Amp event loop builds in coroutine support for all event loop callbacks so we can use the `yield` keyword to make async code feel synchronous. Let's look at a simple example executing inside the event loop run loop:

```php
<?php

use Amp\Loop;

function asyncMultiply($x, $y) {
    yield new Amp\Pause($millisecondsToPause = 100);
    return $x * $y;
}

Loop::run(function () {
    try {
        // Yield control until the generator resolves
        // and return its eventual result.
        $result = yield from asyncMultiply(2, 21); // int(42)
    } catch (Exception $e) {
        // If promise resolution fails the exception is
        // thrown back to us and we handle it as needed.
    }
});
```

As you can see in the above example there is no need for callbacks or `.then()` chaining. Instead,
we're able to use `yield` statements to control program flow even when future computational results
are still pending.

> **NOTE**
>
> Any time a generator yields an `Amp\Promise` there exists the possibility that the associated async operation(s) could fail. When this happens the appropriate exception is thrown back into the calling generator. Applications should generally wrap their promise yields in `try/catch` blocks as an error handling mechanism in this case.

### Subgenerators

As of PHP 7 you can use `yield from` to delegate a sub task to another generator. That generator will be embedded into the currently running generator.

### Yield Behavior

All yields must be one of the following three types:

| Yieldable     | Description                                                                                                                                                                                                                      |
| --------------| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Amp\Promise` | Any promise instance may be yielded and control will be returned to the generator once the promise resolves. If resolution fails the relevant exception is thrown into the generator and must be handled by the application or it will bubble up. If resolution succeeds the promise's resolved value is sent back into the generator. |
| `React\Promise\PromiseInterface` | Same as `Amp\Promise`. Any React promise will automatically be adapted to an Amp promise. |
| `array` | Yielding an array of promises combines them implicitly using `Amp\all`. An array with elements not being promises will result in an `Amp\InvalidYieldError`. |

## Helpers

### `pipe()`

Takes a `Promise` as first and a `callable` as second argument. Upon resolution of the promise, the `callable` is invoked in case of a success and can be used to transform the value. The returned promise resolves to the returned value in case of a success. In case of a thrown exception or promise failure, the promise is failed with that exception.

### `promises()`

Normalizes an array of mixed values / Promises / Promisors to an array of promises.

### `timeout()`

Takes a `Promise` as first and timeout in milliseconds as second parameter. Returns a promise that's resolved / failed with the original promise's return value / failure reason or a `TimeoutException` in case the given promise doesn't resolve within the specified timeout.

### `coroutine()`

Transforms a `callable` given as first argument into a coroutine function.

### `wait()`

Block script execution indefinitely until the specified `Promise` resolves. The `Promise` is passed as the first and only argument.

In the event of promise failure this method will throw the exception responsible for the failure. Otherwise the promise's resolved value is returned.

This function should only be used outside of `Loop::run` when mixing synchronous and asynchronous code.
