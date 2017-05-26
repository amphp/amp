---
layout: docs
title: Coroutines
permalink: /coroutines/
---
Coroutines are interruptible functions. In PHP they can be implemented using [generators](http://php.net/manual/en/language.generators.overview.php).

While generators are usually used to implement simple iterators and yielding elements using the `yield` keyword, Amp uses `yield` as interruption points. When a coroutine yields a value, execution of the coroutine is temporarily interrupted, allowing other tasks to be run, such as I/O handlers, timers, or other coroutines.

## Yield Behavior

All yields must be one of the following three types:

| Yieldable     | Description                                                                                                                                                                                                                      |
| --------------| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Amp\Promise` | Any promise instance may be yielded and control will be returned to the coroutine once the promise resolves. If resolution fails the relevant exception is thrown into the generator and must be handled by the application or it will bubble up. If resolution succeeds the promise's resolved value is sent back into the generator. |
| `React\Promise\PromiseInterface` | Same as `Amp\Promise`. Any React promise will automatically be adapted to an Amp promise. |
| `array` | Yielding an array of promises combines them implicitly using `Amp\Promise\all()`. An array with elements not being promises will result in an `Amp\InvalidYieldError`. |

## Example

```php
<?php

use Amp\Loop;

function asyncMultiply($x, $y): Amp\Promise {
    // Use Amp\call to always return promise instead of a \Generator.
    // Generators are an implementation detail that shouldn't be leaked to API consumers.
    return Amp\call(function () use ($x, $y) {
        yield new Amp\Delayed($millisecondsToPause = 100);
        return $x * $y;        
    });
}

Loop::run(function () {
    try {
        // Yield control until the generator resolves
        // and return its eventual result.
        $result = yield asyncMultiply(2, 21); // int(42)
    } catch (Exception $e) {
        // If promise resolution fails the exception is
        // thrown back to us and we handle it as needed.
    }
});
```

Note that **no callbacks need to be registered** to consume promises and **errors can be handled with ordinary `catch` clauses**, which will bubble up to the calling context if uncaught in the same way exceptions bubble up in synchronous code.
