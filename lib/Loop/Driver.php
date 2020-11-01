<?php

namespace Amp\Loop;

interface Driver extends \FiberScheduler
{
    /**
     * Run the event loop.
     *
     * One iteration of the loop is called one "tick". A tick covers the following steps:
     *
     *  1. Activate watchers created / enabled in the last tick / before `run()`.
     *  2. Execute all enabled defer watchers.
     *  3. Execute all due timer, pending signal and actionable stream callbacks, each only once per tick.
     *
     * The loop MUST continue to run until it is either stopped explicitly, no referenced watchers exist anymore, or an
     * exception is thrown that cannot be handled. Exceptions that cannot be handled are exceptions thrown from an
     * error handler or exceptions that would be passed to an error handler but none exists to handle them.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Stop the event loop.
     *
     * When an event loop is stopped, it continues with its current tick and exits the loop afterwards. Multiple calls
     * to stop MUST be ignored and MUST NOT raise an exception.
     *
     * @return void
     */
    public function stop(): void;

    /**
     * @return bool True if the event loop is running, false if it is stopped.
     */
    public function isRunning(): bool;

    /**
     * Defer the execution of a callback.
     *
     * The deferred callable MUST be executed before any other type of watcher in a tick. Order of enabling MUST be
     * preserved when executing the callbacks.
     *
     * The created watcher MUST immediately be marked as enabled, but only be activated (i.e. callback can be called)
     * right before the next tick. Callbacks of watchers MUST NOT be called in the tick they were enabled.
     *
     * @param callable (string $watcherId, mixed $data) $callback The callback to defer. The `$watcherId` will be
     *                    invalidated before the callback call.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public function defer(callable $callback, $data = null): string;

    /**
     * Delay the execution of a callback.
     *
     * The delay is a minimum and approximate, accuracy is not guaranteed. Order of calls MUST be determined by which
     * timers expire first, but timers with the same expiration time MAY be executed in any order.
     *
     * The created watcher MUST immediately be marked as enabled, but only be activated (i.e. callback can be called)
     * right before the next tick. Callbacks of watchers MUST NOT be called in the tick they were enabled.
     *
     * @param int   $delay The amount of time, in milliseconds, to delay the execution for.
     * @param callable (string $watcherId, mixed $data) $callback The callback to delay. The `$watcherId` will be
     *                     invalidated before the callback call.
     * @param mixed $data  Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public function delay(int $delay, callable $callback, $data = null): string;

    /**
     * Repeatedly execute a callback.
     *
     * The interval between executions is a minimum and approximate, accuracy is not guaranteed. Order of calls MUST be
     * determined by which timers expire first, but timers with the same expiration time MAY be executed in any order.
     * The first execution is scheduled after the first interval period.
     *
     * The created watcher MUST immediately be marked as enabled, but only be activated (i.e. callback can be called)
     * right before the next tick. Callbacks of watchers MUST NOT be called in the tick they were enabled.
     *
     * @param int   $interval The time interval, in milliseconds, to wait between executions.
     * @param callable (string $watcherId, mixed $data) $callback The callback to repeat.
     * @param mixed $data     Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public function repeat(int $interval, callable $callback, $data = null): string;

    /**
     * Execute a callback when a stream resource becomes readable or is closed for reading.
     *
     * Warning: Closing resources locally, e.g. with `fclose`, might not invoke the callback. Be sure to `cancel` the
     * watcher when closing the resource locally. Drivers MAY choose to notify the user if there are watchers on invalid
     * resources, but are not required to, due to the high performance impact. Watchers on closed resources are
     * therefore undefined behavior.
     *
     * Multiple watchers on the same stream MAY be executed in any order.
     *
     * The created watcher MUST immediately be marked as enabled, but only be activated (i.e. callback can be called)
     * right before the next tick. Callbacks of watchers MUST NOT be called in the tick they were enabled.
     *
     * @param resource $stream The stream to monitor.
     * @param callable (string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
     * @param mixed    $data   Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public function onReadable($stream, callable $callback, $data = null): string;

    /**
     * Execute a callback when a stream resource becomes writable or is closed for writing.
     *
     * Warning: Closing resources locally, e.g. with `fclose`, might not invoke the callback. Be sure to `cancel` the
     * watcher when closing the resource locally. Drivers MAY choose to notify the user if there are watchers on invalid
     * resources, but are not required to, due to the high performance impact. Watchers on closed resources are
     * therefore undefined behavior.
     *
     * Multiple watchers on the same stream MAY be executed in any order.
     *
     * The created watcher MUST immediately be marked as enabled, but only be activated (i.e. callback can be called)
     * right before the next tick. Callbacks of watchers MUST NOT be called in the tick they were enabled.
     *
     * @param resource $stream The stream to monitor.
     * @param callable (string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
     * @param mixed    $data   Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public function onWritable($stream, callable $callback, $data = null): string;

    /**
     * Execute a callback when a signal is received.
     *
     * Warning: Installing the same signal on different instances of this interface is deemed undefined behavior.
     * Implementations MAY try to detect this, if possible, but are not required to. This is due to technical
     * limitations of the signals being registered globally per process.
     *
     * Multiple watchers on the same signal MAY be executed in any order.
     *
     * The created watcher MUST immediately be marked as enabled, but only be activated (i.e. callback can be called)
     * right before the next tick. Callbacks of watchers MUST NOT be called in the tick they were enabled.
     *
     * @param int   $signo The signal number to monitor.
     * @param callable (string $watcherId, int $signo, mixed $data) $callback The callback to execute.
     * @param mixed $data  Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     *
     * @throws UnsupportedFeatureException If signal handling is not supported.
     */
    public function onSignal(int $signo, callable $callback, $data = null): string;

    /**
     * Enable a watcher to be active starting in the next tick.
     *
     * Watchers MUST immediately be marked as enabled, but only be activated (i.e. callbacks can be called) right before
     * the next tick. Callbacks of watchers MUST NOT be called in the tick they were enabled.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     *
     * @throws InvalidWatcherError If the watcher identifier is invalid.
     */
    public function enable(string $watcherId): void;

    /**
     * Cancel a watcher.
     *
     * This will detach the event loop from all resources that are associated to the watcher. After this operation the
     * watcher is permanently invalid. Calling this function MUST NOT fail, even if passed an invalid watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public function cancel(string $watcherId): void;

    /**
     * Disable a watcher immediately.
     *
     * A watcher MUST be disabled immediately, e.g. if a defer watcher disables a later defer watcher, the second defer
     * watcher isn't executed in this tick.
     *
     * Disabling a watcher MUST NOT invalidate the watcher. Calling this function MUST NOT fail, even if passed an
     * invalid watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public function disable(string $watcherId): void;

    /**
     * Reference a watcher.
     *
     * This will keep the event loop alive whilst the watcher is still being monitored. Watchers have this state by
     * default.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     *
     * @throws InvalidWatcherError If the watcher identifier is invalid.
     */
    public function reference(string $watcherId): void;

    /**
     * Unreference a watcher.
     *
     * The event loop should exit the run method when only unreferenced watchers are still being monitored. Watchers
     * are all referenced by default.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public function unreference(string $watcherId): void;

    /**
     * Stores information in the loop bound registry.
     *
     * Stored information is package private. Packages MUST NOT retrieve the stored state of other packages. Packages
     * MUST use their namespace as prefix for keys. They may do so by using `SomeClass::class` as key.
     *
     * If packages want to expose loop bound state to consumers other than the package, they SHOULD provide a dedicated
     * interface for that purpose instead of sharing the storage key.
     *
     * @param string $key   The namespaced storage key.
     * @param mixed  $value The value to be stored.
     *
     * @return void
     */
    public function setState(string $key, mixed $value): void;

    /**
     * Gets information stored bound to the loop.
     *
     * Stored information is package private. Packages MUST NOT retrieve the stored state of other packages. Packages
     * MUST use their namespace as prefix for keys. They may do so by using `SomeClass::class` as key.
     *
     * If packages want to expose loop bound state to consumers other than the package, they SHOULD provide a dedicated
     * interface for that purpose instead of sharing the storage key.
     *
     * @param string $key The namespaced storage key.
     *
     * @return mixed The previously stored value or `null` if it doesn't exist.
     */
    public function getState(string $key): mixed;

    /**
     * Set a callback to be executed when an error occurs.
     *
     * The callback receives the error as the first and only parameter. The return value of the callback gets ignored.
     * If it can't handle the error, it MUST throw the error. Errors thrown by the callback or during its invocation
     * MUST be thrown into the `run` loop and stop the driver.
     *
     * Subsequent calls to this method will overwrite the previous handler.
     *
     * @param callable(\Throwable $error):void|null $callback The callback to execute. `null` will clear the
     *     current handler.
     *
     * @return callable(\Throwable $error):void|null The previous handler, `null` if there was none.
     */
    public function setErrorHandler(callable $callback = null): ?callable;

    /**
     * Returns the current loop time in millisecond increments. Note this value does not necessarily correlate to
     * wall-clock time, rather the value returned is meant to be used in relative comparisons to prior values returned
     * by this method (intervals, expiration calculations, etc.) and is only updated once per loop tick.
     *
     * Extending classes should override this function to return a value cached once per loop tick.
     *
     * @return int
     */
    public function now(): int;

    /**
     * Get the underlying loop handle.
     *
     * Example: the `uv_loop` resource for `libuv` or the `EvLoop` object for `libev` or `null` for a native driver.
     *
     * Note: This function is *not* exposed in the `Loop` class. Users shall access it directly on the respective loop
     * instance.
     *
     * @return null|object|resource The loop handle the event loop operates on. `null` if there is none.
     */
    public function getHandle();

    /**
     * Returns the same array of data as getInfo().
     *
     * @return array
     */
    public function __debugInfo(): array;

    /**
     * Retrieve an associative array of information about the event loop driver.
     *
     * The returned array MUST contain the following data describing the driver's currently registered watchers:
     *
     *     [
     *         "defer"            => ["enabled" => int, "disabled" => int],
     *         "delay"            => ["enabled" => int, "disabled" => int],
     *         "repeat"           => ["enabled" => int, "disabled" => int],
     *         "on_readable"      => ["enabled" => int, "disabled" => int],
     *         "on_writable"      => ["enabled" => int, "disabled" => int],
     *         "on_signal"        => ["enabled" => int, "disabled" => int],
     *         "enabled_watchers" => ["referenced" => int, "unreferenced" => int],
     *     ];
     *
     * Implementations MAY optionally add more information in the array but at minimum the above `key => value` format
     * MUST always be provided.
     *
     * @return array Statistics about the loop in the described format.
     */
    public function getInfo(): array;

    /**
     * Removes all watchers, registry data, and error handler from the event loop. This method is intended for
     * clearing the loop between tests and not intended for use in an application.
     */
    public function clear(): void;
}
