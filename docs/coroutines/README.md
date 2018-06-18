---
layout: docs
title: Coroutines
permalink: /coroutines/
---
Coroutines are interruptible functions. In PHP they can be implemented using [generators](http://php.net/manual/en/language.generators.overview.php).

While generators are usually used to implement simple iterators and yielding elements using the `yield` keyword, Amp uses `yield` as interruption points. When a coroutine yields a value, execution of the coroutine is temporarily interrupted, allowing other tasks to be run, such as I/O handlers, timers, or other coroutines.

```php
// Fetches a resource with Artax and returns its body.
$promise = Amp\call(function () use ($http) {
    try {
        // Yield control until the generator resolves
        // and return its eventual result.
        $response = yield $http->request("https://example.com/");

        $body = yield $response->getBody();

        return $body;
    } catch (HttpException $e) {
        // If promise resolution fails the exception is
        // thrown back to us and we handle it as needed.
    }
});
```

Every time a promise is `yield`ed, the coroutine subscribes to the promise and automatically continues it once the promise resolved.
On successful resolution the coroutine will send the resolution value into the generator using [`Generator::send()`](https://secure.php.net/generator.send).
On failure it will throw the exception into the generator using [`Generator::throw()`](https://secure.php.net/generator.throw).
This allows writing asynchronous code almost like synchronous code.

Note that no callbacks need to be registered to consume promises and errors can be handled with ordinary `catch` clauses, which will bubble up to the calling context if uncaught in the same way exceptions bubble up in synchronous code.

{:.note}
> Use `Amp\call()` to always return a promise instead of a `\Generator` from your public APIs. Generators are an implementation detail that shouldn't be leaked to API consumers.

## Yield Behavior

All `yield`s in a coroutine must be one of the following three types:

| Yieldable     | Description                                                                                                                                                                                                                      |
| --------------| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Amp\Promise` | Any promise instance may be yielded and control will be returned to the coroutine once the promise resolves. If resolution fails the relevant exception is thrown into the generator and must be handled by the application or it will bubble up. If resolution succeeds the promise's resolved value is sent back into the generator. |
| `React\Promise\PromiseInterface` | Same as `Amp\Promise`. Any React promise will automatically be adapted to an Amp promise. |
| `array` | Yielding an array of promises combines them implicitly using `Amp\Promise\all()`. An array with elements not being promises will result in an `Amp\InvalidYieldError`. |

## Yield vs. Yield From

`yield` is used to "await" promises, `yield from` can be used to delegate to a sub-routine. `yield from` should only be used to delegate to private methods, any public API should always return promises instead of generators.

When a promise is yielded from within a `\Generator`, `\Generator` will be paused and continue as soon as the promise is resolved. Use `yield from` to yield another `\Generator`. Instead of using `yield from`, you can also use `yield new Coroutine($this->bar());` or `yield call([$this, "bar"]);`.

An example:

```php
class Foo
{
    public function delegationWithCoroutine(): Amp\Promise
    {
        return new Amp\Coroutine($this->bar());
    }

    public function delegationWithYieldFrom(): Amp\Promise
    {
        return Amp\call(function () {
            return yield from $this->bar();
        });
    }

    public function delegationWithCallable(): Amp\Promise
    {
        return Amp\call([$this, 'bar']);
    }

    public function bar(): Generator
    {
        yield new Amp\Success(1);
        yield new Amp\Success(2);
        return yield new Amp\Success(3);
    }
}

Amp\Loop::run(function () {
    $foo = new Foo();
    $r1 = yield $foo->delegationWithCoroutine();
    $r2 = yield $foo->delegationWithYieldFrom();
    $r3 = yield $foo->delegationWithCallable();
    var_dump($r1);
    var_dump($r2);
    var_dump($r3);
});
```

Outputs:
```
int(3)
int(3)
int(3)
```

For further information about `yield from`, consult the [PHP manual](http://php.net/manual/en/language.generators.syntax.php#control-structures.yield.from).
