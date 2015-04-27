### master

> **BC BREAKS:**

- Dropped `\Amp\getReactor` param `$forceNew` as it doesn't has a proper use case.

v0.16.0
-------

- Added `Reactor::coroutine()` method
- Added `Amp\coroutine()` function
- `YieldCommands` "enum" constant class removed -- yield keys now live in
  the reactor class
- New optional `"coroutine"` yield key for self-documenting generator
  yields.
- New optional `"async"` yield key for self-documenting promise yields.
- New `"return"` yield key for specifying the return value of a resolved
  Generator coroutine. If not specified a resolved coroutine result is
  equal to null.
- The final value yielded by a resolved `Generator` is *no longer* used
  as its "return" value. Instead, generators must manually use the new
  `"return"` yield key specifically to designate the value that should
  be used to resolve the promise associated with generator resolution.
- `GeneratorResolver` trait renamed to `CoroutineResolver` and is now an
  abstract class extended by the various `Reactor` implementations.
- Implicit "all" array combinator resolution is now removed. Use the
  explicit form instead:

```php
function() {
    list($a, $b, $c) = (yield 'all' => [$promise1, $promise2, $promise3]);
};
```

### v0.15.3

- Correctly re-enable disabled stream polling watchers in UvReactor

### v0.15.2

- Fix immediately watcher starvation in extension reactors (Bob Weinand)

### v0.15.1

- Throw exceptions in extension reactor if required extensions not loaded
  (Douglas Gontijo)
- Fix outdated examples (Douglas Gontijo)
- Fix `E_NOTICE` errors in `UvReactor`


v0.15.0
-------

**Additions**

- Added `Reactor::__debugInfo()` hook to ease debugging.
- Added `Reactor::onError()` exception handling hook to handle asynchronous
  errors without breaking the event loop
- Added optional boolean `$noWait` parameter to `Reactor::tick($noWait)`
- Added `Amp\getReactor()` and `Amp\chooseReactor()` functions
- Added `Amp\wait()` to replace deprecated `Amp\Promise::wait()`
- Added new `"bind"` yield command

**Removals:**

- Removed `Combinator` class in favor of combinator functions
- Removed `Resolver` class, use `GeneratorResolver` trait internally
- `Promisor` implementations no longer have any knowledge of the event reactor.

**Deprecations:**

- Deprecated `Promise::wait()`. New code should use `Amp\wait()` to synchronously
  wait for promise completion
- Deprecated `Amp\reactor()` function. New code should use `Amp\getReactor()`
  instead
- The `ReactorFactory` class is deprecated and scheduled for removal. Please use
  the `Amp\getReactor()` function instead of `ReactorFactory::select()`

**Bugfixes:**

- Correctly break out of the `NativeReactor` run loop immediately when
  `Reactor::stop()` invoked inside immediately watchers
- Correctly exit `UvReactor` and `LibeventReactor` run loop when no outstanding
  watchers remain active
- Other miscellaneous fixes

**Other:**

- Changed `"wait"` yield command to `"pause"`

> **BC BREAKS:**

- None

v0.14.0
-------

- Improve public property struct safety with new `Struct` class
- Prevent breakage if missing `ext/openssl` with libuv socket watchers in windows
- Allow multiple IO watchers for streams using the libuv reactor
- Don't hammer the CPU using NOWAIT ticks in the libuv reactor

> **BC BREAKS:**

- none

v0.13.0
-------

- Remove `watchStream()` alias from all reactor implementations. Aliases only cause
  confusion.
- Formalize generator resolution `YieldCommands`, remove `watch-stream`, add
  `onreadable` and `onwritable` yield commands.
- Add new `nowait` yield command and the nowait prefix, `@` to optionally continue
  generator processing without waiting for individual asynchronous results.

> **BC BREAKS:**

- All code using `watchStream()` must update to use the specific `onReadable()` and
  `onWritable()` reactor functions as `watchStream()` has been removed.
- The `watch-stream` yield command has been removed. Code should be updated to use the
  new `onreadable` and `onwritable` yield commands.

### v0.12.1

- Use reactor singleton instance in global functions

v0.12.0
-------

- Generator resolution now accepts string keys to simplify reactor operations via yield
- Fix Promise memory leak + tick starvation when resolving Generator yields inside loops
- Fix infinite loop on nested immediately watchers in `LibeventReactor`
- Rename `any()` combinator -> `some()`
- `any()` combinator will now *never* fail.

> **BC BREAKS:**

- The `any()` combinator no longer fails even if all promises fail. Code wishing for
  the previous behavior should change references to `some()` which will only fail if
  all promises in the group resolve as failures.


### v0.11.4

- Fix syntax error :(

### v0.11.3

- Fix missing parameters in map/filter

### v0.11.2

- Use global reactor if not passed to Future::__construct()

### v0.11.1

- Fix bug causing immediate() callback starvation in NativeReactor

v0.11.0
-------

- Added Combinator class
- Watcher IDs are now strings to avoid array key collisions after reaching PHP_INT_MAX keys
- Watcher IDs now begin at one instead of zero making boolean checks for watcher ID
  existence safe in all scenarios (because a "0" string is never possible)
- Add `LibeventReactor::getUnderlyingLoop()` for access to lower-level libevent functionality
- Add `UvReactor::getUnderlyingLoop()` for access to lower-level php-uv functionality
- `Reactor::immediately()` watchers are now always enacted in a fresh call stack in the next
  iteration of the event loop. They may still be disabled/enabled/cancelled like any other watcher.
- `Reactor::at()` implementations now accept unix timestamps in addition to strtotime() parsable
  strings at parameter 2.
- Implement `Alert\SignalReactor` interface in `Alert\UvReactor` for signal handling support
- Fix UvReactor memory leak where one-time watchers were never cleared
- Miscellaneous cleanup

> **BC BREAKS:**

- The following Reactor flags for use with `Reactor::watchStream()` have been renamed:
    * Reactor::POLL_READ  -> Reactor::WATCH_READ
    * Reactor::POLL_WRITE -> Reactor::WATCH_WRITE
    * Reactor::ENABLE_NOW -> Reactor::WATCH_NOW
- The `Reactor::POLL_SOCK` constant has been removed
- Callback parameter order has changed and is now standardized for all watcher types:
    - timers = func($reactor, $watcherId)
    - stream = func($reactor, $watcherId, $stream)
    - signal = func($reactor, $watcherId, $signo)
- The parameter order in `Reactor::watchStream()` and `watchStream()` has changed.

#### v0.10.2

- Improved perf in procedural functions with static caching to avoid fcall overhead
- Improved function documentation
- Unit test cleanup

#### v0.10.1

- Fixed syntax goof causing E_PARSE in `Alert\ReactorFactory`

v0.10.0
-------

- Added *functions.php* API for reactor use in procedural and functional code.
- `ReactoryFactory::select()` is now a static singleton method. Single-threaded code should never
  use multiple event loops. This change is made to ease `Reactor` procurement and minimize bugs
  from the existence of multiple `Reactor` instances in the same thread. It is *NOT*, however, an
  excuse to forego dependency injection. Do not abuse the global nature of the event loop. Lazy
  injection is fine, but laziness on your part as a programmer is not.

> **BC BREAKS:**

- The `ReactorFactory::__invoke()` magic method has been removed. Any code relying on it must migrate
  references to `ReactoryFactory::select()`

v0.9.0
------

- Reactor instance now passed to optional $onStart callbacks when `Reactor::run()` is called.
- Add new libuv reactor support (`UvReactor`) via the [php-uv extension](https://github.com/chobie/php-uv).
  The php-uv extension must be built [against commit 75fd2ff591](https://github.com/chobie/php-uv/commit/75fd2ff591de2d3571985437de4465dfe8687753) or newer.
- Add `Reactor::watchStream()` alternative for IO watching. The `$flags` bitmask
  paves the way for additional option specs in the libuv reactor without needlessly complicating the
  interface.
- Internal watcher IDs now increment from zero instead of PHP_INT_MAX*-1

> **NO BC BREAKS**

#### v0.8.1

- Fix bug preventing `NativeReactor` from relinquishing control when no timers or
  stream watchers exist.
- Fix broken `Reactor::at` millisecond resolution.

v0.8.0
------

- Add new `SignalReactor` for capturing and reacting to POSIX signals
- `LibeventReactor` now implements `SignalReactor`
- Remove all concurrency primitives (moved to new After repo)

> **BC BREAKS**:

- Any existing code relying on the Future/Promise/etc concurrency primitives must
  now use the separate After repo as things files are no longer included with Alert.

#### v0.7.1

- `PromiseGroup` now transparently succeeds instead of throwing on empty futures array
- `stream_select()` errors suppressed in `NativeReactor` to silence errors on signal interrupts

v0.7.0
------

- `Future` is now an interface
- Add `Unresolved` as the default pending `Future` (`Promise->getFuture()`)
- Add immutable resolved `Failure` and `Success` futures

v0.6.0
------

- Time intervals are now expected in milliseconds and not seconds.
- Cleaned up unit tests

> **BC BREAKS**:

- Existing interval and delay times must be multiplied x 1000 to retain the same behavior.


v0.5.0
------

- Pare down the Promise/Future APIs
- Minor performance improvements

> **BC BREAKS**:

- Removed `Future::isPending()`
- Removed `Future::failed()`
- Removed `Future::onSuccess()`
- Removed `Future::onFailure()`

v0.4.0
------

- Altered watcher ID generation to avoid potential collisions
- Added optional $onStart callback parameter to Reactor::run() implementations
- Added Scala-like Future\Promise implementation
- Remove `Forkable` things originally added in v0.2.0 (unnecessary)

> **BC BREAKS**: *none*

v0.3.0
------

- Timed event callbacks now passed the reactor instance at param 2 upon invocation
- IO callbacks now passed the reactor instance at param 3 upon invocation
- Minor bugfixes/improvements

> **BC BREAKS**: *none*

v0.2.0
------

- Added `Alert\Forkable` interface for safely forking event reactors without resource corruption
- `Alert\LibeventReactor` now implements `Alert\Forkable`

> **BC BREAKS**: *none*

#### v0.1.2

- Addressed execution time drift in repeating native reactor alarms

#### v0.1.1

- Addressed infinite recursion in repeating callbacks

v0.1.0
------

- Initial tagged release
