## 3.x

### 3.0.0

#### Event Loop

Amp no longer ships its own event loop. It's now based on [Revolt](https://revolt.run). `Revolt\EventLoop` is quite similar to Amp's previous `Amp\Loop`. A very important difference is using `float $seconds` instead of `int $milliseconds` for timers though!

#### Promises

`Future` is a replacement for the previous `Promise`.
There's no need for callbacks or `yield` anymore!
Its `await()` method is based on fibers and replaces generator based coroutines / `Amp\Promise\wait()`.

- Renamed `Amp\Deferred` to `Amp\DeferredFuture`.
- Removed `Amp\Promise\wait()`: Use `Amp\Future::await()` instead, which can be called in any (nested) context unlike before.
- Removed `Amp\call()`: Remove the passed closure boilerplate and all `yield` keywords, interruption is handled via fibers now instead of generator coroutines.
- Removed `Amp\asyncCall()`: Replace invocations with `Amp\async()`, which starts a new fiber instead of using generators.
- Removed `Amp\coroutine()`: There's no direct replacement.
- Removed `Amp\asyncCoroutine()`: There's no direct replacement.
- Removed `Amp\Promise\timeout()`: `Future::await()` accepts an optional `Cancellation`, which can be used as a replacement.
- Removed `Amp\Promise\rethrow()`: Unhandled errors are now automatically thrown into the event loop, so there's no need for that function anymore.
- Unhandled errors can be ignored using `Future::ignore()` if needed, but should usually be handled in some way.
- Removed `Amp\Promise\wrap()`: Use `Future::finally()` instead.
- Renamed `Amp\getCurrentTime()` to `Amp\now()` returning the time in seconds instead of milliseconds.
- Changed `Amp\delay()` to accept the delay in seconds now instead of milliseconds.
- Added `Amp\weakClosure()` to allow a class to hold a self-referencing Closure without creating a circular reference that prevents automatic garbage collection.
- Added `Amp\trapSignal()` to await one or multiple signals.

#### Promise Combinators

Promise combinators have been renamed:

- `Amp\Promise\race()` has been renamed to `Amp\Future\awaitFirst()`
- `Amp\Promise\first()` has been renamed to `Amp\Future\awaitAny()`
- `Amp\Promise\some()` has been renamed to `Amp\Future\awaitAnyN()`
- `Amp\Promise\any()` has been renamed to `Amp\Future\awaitAll()`
- `Amp\Promise\all()` has been renamed to `Amp\Future\await()`

#### CancellationToken

- `CancellationToken` has been renamed to `Cancellation`.
- `CancellationTokenSource` has been renamed to `DeferredCancellation`.
- `NullCancellationToken` has been renamed to `NullCancellation`.
- `TimeoutCancellationToken` has been renamed to `TimeoutCancellation`.
- `CombinedCancellationToken` has been renamed to `CompositeCancellation`.
- `SignalCancellation` has been added.

#### Iterators

Iterators have been removed from `amphp/amp` as normal PHP iterators can be used with fibers now and there's no need for a separate API.
However, there's still some need for _concurrent_ iterators, which is covered by the new [`amphp/pipeline`](https://github.com/amphp/pipeline) library now.

#### Closable

`Amp\Closable` has been added as a new basic interface for closable resources such as streams or sockets.

#### Strict Types

Strict types now declared in all library files.
This will affect callbacks invoked within this library's code which use scalar types as parameters.
Functions used with `Amp\async()` are the most likely to be affected by this change — these functions will now be invoked within a strict-types context.

## 2.x

Under maintenance, see corresponding branch.

## 1.x

No longer maintained.

## 0.x

No longer maintained.
