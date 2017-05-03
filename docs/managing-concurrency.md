@TODO: Move remaining parts to coroutine docs once they exist.

## Generators

The addition of generators in PHP 5.5 trivializes synchronization and error handling in async contexts. The Amp event loop builds in coroutine support for all event loop callbacks so we can use the `yield` keyword to make async code feel synchronous. Let's look at a simple example executing inside the event loop run loop:

```php
<?php

use Amp\Loop;

function asyncMultiply($x, $y) {
    yield new Amp\Delayed($millisecondsToPause = 100);
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
