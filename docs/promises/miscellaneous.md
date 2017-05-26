---
layout: docs
title: Promise Helpers
permalink: /promises/miscellaneous
---
Amp offers some small promise helpers, namely
 * `Amp\Promise\rethrow()`
 * `Amp\Promise\timeout()`
 * `Amp\Promise\wait()`

## `rethrow()`

`rethrow()` subscribes to the passed `Promise` and forwards all errors to the event loop. That handler can log these failures or the event loop will stop if no such handler exists.

`rethrow()` is useful whenever you want to fire and forget, but still care about any errors that happen.

## `timeout()`

`timeout()` applies a timeout to the passed promise, either resolving with the original value or error reason in case the promise resolves within the timeout period, or otherwise fails the returned promise with an `Amp\TimeoutException`.

Note that `timeout()` does not cancel any operation or frees any resources. If available, use dedicated API options instead, e.g. for socket connect timeouts.

## `wait()`

`wait()` can be used to synchronously wait for a promise to resolve. It returns the result value or throws an exception in case of an error. `wait()` blocks and calls `Loop::run()` internally. It SHOULD NOT be used in fully asynchronous applications, but only when integrating async APIs into an otherwise synchronous application.
