### 2.0.4

 - Allow `AMP_DEBUG` to be defined via the environment.
 - Fix formatting of stack traces used for double resolution debugging.

### 2.0.3

 - `Loop::set()` replaces the current driver with a dummy driver for the time of `gc_collect_cycles()` now. This allows cyclic references to be cleaned up properly before the new driver is set. Without such a fix, cyclic references might have been cleaned up later, e.g. cancelling their watcher IDs on the new loop, thereby cancelling the wrong watchers.
 - Promise combinator functions (`all(), `any()`, `first()`, `some()`) now preserve order of the given `$promises` array argument.

### 2.0.2

 - Fixed warnings and timers in `EventDriver`.
 - Does no longer hide warnings from `stream_select`.

### 2.0.1

 - Fixed an issue where the loop blocks even though all watchers are unreferenced.

2.0.0
-----

 * `Amp\reactor()` has been replaced with `Amp\Loop::set()` and `Amp\Loop::get()`.
 * `Amp\driver()` has been replaced with `Amp\Loop\Factory::create()`.
 * `Amp\tick()` no longer exists and doesn't have a replacement. Ticks are an internal detail.
 * Functions for creating and managing watchers are now static methods of `Amp\Loop` instead of functions in the `Amp` namespace.
   * `once()` is now `delay()` and `immediately()` is `defer()`.
   * Parameter order for `delay()` and `repeat()` has been changed.
   * `reference()` and `unreference()` have been added.
 * `Amp\Pause` has been renamed to `Amp\Delayed` and accepts an optional resolution value now. Additionally `reference()` and `unreference()` methods have been added.
 * Promise accepting functions have been moved to the `Amp\Promise` namespace.
 * `Amp\Promise\some()` accepts an additional `$required` parameter.
 * `Amp\call()`, `Amp\asyncCall()`, `Amp\coroutine()` and `Amp\asyncCoroutine()` have been added.
 * `Amp\resolve()` has been removed, use `Amp\call()` instead.
 * `Promise::when()` has been renamed to `Promise::onResolve()`
 * `Promise::watch()` has been removed, use `Amp\Iterator`, [`amphp/byte-stream`](https://github.com/amphp/byte-stream) or a custom implementation that implements `Amp\Promise` instead and provides dedicated APIs to access the previously data shared via the `watch()` mechanism.
 * `Amp\Iterator`, `Amp\Emitter` and `Amp\Producer` have been added with several functions in the `Amp\Iterator` namespace.
 * Various other changes.

### 1.2.2

- Fix notice in `NativeReactor` when removing a handle while
  an event is waiting for it. (Regression fix from 1.1.1)

### 1.2.1

- Fix `uv_run()` potentially exiting earlier than intended,
  leading to an infinite loop in `UvReactor::run()`.

1.2.0
-----

- `resolve()` now also accepts callables returning generators.

### 1.1.1

- Fix memory leak in `NativeReactor`, retaining an empty array
  for each stream.
- Remove circular references in `UvReactor` to avoid garbage
  collector calls.

1.1.0
-----

- Add `getExceptions()` method to `CombinatorException` to get an
  array of all the exceptions (affecting `some()` and `first()`).
- Fix `NativeReactor` not ending up in stopped state if primary
  callback didn't install any events.

### 1.0.8

- Fix `NativeReactor` running a busy loop if no timers are active.
  Properly block now in NativeReactor inside `stream_select()`.

### 1.0.7

- Several combinator functions could result in a promise already
  resolved exception in case some values of the array weren't
  promises.

### 1.0.6

- Fix issue in `NativeReactor` causing `stop()` to be delayed by
  one second.

### 1.0.5

- Convert general `RuntimeException` to more specific
  `Amp\CombinatorException`.

### 1.0.4

- Repeat watchers in `LibeventReactor` internally were handled in
  microsecond intervals instead of milliseconds.

### 1.0.3

- Fix issue in `NativeReactor` capable of causing keep alive
  counter corruption when a watcher was cancelled inside its
  own callback.
- Fix issue in `UvReactor` with `libuv` >= 1.1.0 causing busy loop
  with immediates present, but no watchers being triggered.

### 1.0.2

- Fix PHP 7 issue in which top-level `Throwable`s weren't caught
  in certain coroutine contexts.
- Remove error suppression operator on optionally `null` option
  assignment to avoid spurious `E_NOTICE` output when custom
  error handlers are used.

### 1.0.1

- Fix bug preventing `UvReactor::tick()` from returning when no
  events are ready for a single active IO watcher.

1.0.0
-----

- Initial stable API release
