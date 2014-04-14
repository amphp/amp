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
