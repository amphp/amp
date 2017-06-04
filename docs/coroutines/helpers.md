---
layout: docs
title: Coroutine Helpers
permalink: /coroutines/helpers
---
`Amp\Coroutine` requires an already instantiated `Generator` to be passed to its constructor. Always calling a callable before passing the `Generator` to `Amp\Coroutine` is unnecessary boilerplate.

## `coroutine()`

Returns a new function that wraps `$callback` in a promise/coroutine-aware function that automatically runs generators as coroutines. The returned function always returns a promise when invoked. Errors have to be handled by the callback caller or they will go unnoticed.

```php
function coroutine(callable $callback): callable { ... }
```

Use this function to create a coroutine-aware callable for a promise-aware callback caller.

## `asyncCoroutine()`

Same as `coroutine()` but doesn't return a `Promise` when the returned callback is called. Instead, promises are passed to `Amp\Promise\rethrow()` to handle errors automatically.

## `call()`

```php
function call(callable $callback, ...$args): Promise { ... }
```

Calls the given function, always returning a promise. If the function returns a `Generator`, it will be run as a coroutine. If the function throws, a failed promise will be returned.

`call($callable, ...$args)` is equivalent to `coroutine($callable)(...$args)`.

## `asyncCall()`

```php
function asyncCall(callable $callback, ...$args) { ... }
```

Same as `call()`, but doesn't return the `Promise`. Promises are automatically passed to `Amp\Promise\rethrow` for error handling.
