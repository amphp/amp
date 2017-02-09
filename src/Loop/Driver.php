<?php

namespace AsyncInterop\Loop;

/**
 * Event loop driver which implements all basic operations to allow interoperability.
 *
 * Watchers (enabled or new watchers) MUST immediately be marked as enabled, but only be activated (i.e. callbacks can
 * be called) right before the next tick. Callbacks of watchers MUST NOT be called in the tick they were enabled.
 *
 * All registered callbacks MUST NOT be called from a file with strict types enabled (`declare(strict_types=1)`).
 */
abstract class Driver
{
    /**
     * @var array
     */
    private $registry = [];

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
    abstract public function run();

    /**
     * Stop the event loop.
     *
     * When an event loop is stopped, it continues with its current tick and exits the loop afterwards. Multiple calls
     * to stop MUST be ignored and MUST NOT raise an exception.
     *
     * @return void
     */
    abstract public function stop();

    /**
     * Defer the execution of a callback.
     *
     * The deferred callable MUST be executed in the next tick of the event loop and before any other type of watcher.
     * Order of enabling MUST be preserved when executing the callbacks.
     *
     * @param callable(string $watcherId, mixed $data) $callback The callback to defer. The `$watcherId` will be
     *     invalidated before the callback call.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    abstract public function defer(callable $callback, $data = null);

    /**
     * Delay the execution of a callback.
     *
     * The delay is a minimum and approximate, accuracy is not guaranteed. Order of calls MUST be determined by which
     * timers expire first, but timers with the same expiration time MAY be executed in any order.
     *
     * @param int $delay The amount of time, in milliseconds, to delay the execution for.
     * @param callable(string $watcherId, mixed $data) $callback The callback to delay. The `$watcherId` will be
     *     invalidated before the callback call.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    abstract public function delay($delay, callable $callback, $data = null);

    /**
     * Repeatedly execute a callback.
     *
     * The interval between executions is a minimum and approximate, accuracy is not guaranteed. Order of calls MUST be
     * determined by which timers expire first, but timers with the same expiration time MAY be executed in any order.
     * The first execution is scheduled after the first interval period.
     *
     * @param int $interval The time interval, in milliseconds, to wait between executions.
     * @param callable(string $watcherId, mixed $data) $callback The callback to repeat.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    abstract public function repeat($interval, callable $callback, $data = null);

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
     * @param resource $stream The stream to monitor.
     * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    abstract public function onReadable($stream, callable $callback, $data = null);

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
     * @param resource $stream The stream to monitor.
     * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    abstract public function onWritable($stream, callable $callback, $data = null);

    /**
     * Execute a callback when a signal is received.
     *
     * Warning: Installing the same signal on different instances of this interface is deemed undefined behavior.
     * Implementations MAY try to detect this, if possible, but are not required to. This is due to technical
     * limitations of the signals being registered globally per process.
     *
     * Multiple watchers on the same signal MAY be executed in any order.
     *
     * @param int $signo The signal number to monitor.
     * @param callable(string $watcherId, int $signo, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     *
     * @throws UnsupportedFeatureException If signal handling is not supported.
     */
    abstract public function onSignal($signo, callable $callback, $data = null);

    /**
     * Enable a watcher.
     *
     * Watchers MUST immediately be marked as enabled, but only be activated (i.e. callbacks can be called) right before
     * the next tick. Callbacks of watchers MUST NOT be called in the tick they were enabled.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     *
     * @throws InvalidWatcherException If the watcher identifier is invalid.
     */
    abstract public function enable($watcherId);

    /**
     * Disable a watcher.
     *
     * Disabling a watcher MUST NOT invalidate the watcher. Calling this function MUST NOT fail, even if passed an
     * invalid watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    abstract public function disable($watcherId);

    /**
     * Cancel a watcher.
     *
     * This will detatch the event loop from all resources that are associated to the watcher. After this operation the
     * watcher is permanently invalid. Calling this function MUST NOT fail, even if passed an invalid watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    abstract public function cancel($watcherId);

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
     * @throws InvalidWatcherException If the watcher identifier is invalid.
     */
    abstract public function reference($watcherId);

    /**
     * Unreference a watcher.
     *
     * The event loop should exit the run method when only unreferenced watchers are still being monitored. Watchers
     * are all referenced by default.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     *
     * @throws InvalidWatcherException If the watcher identifier is invalid.
     */
    abstract public function unreference($watcherId);

    /**
     * Stores information in the loop bound registry.
     *
     * This can be used to store loop bound information. Stored information is package private. Packages MUST NOT
     * retrieve the stored state of other packages. Packages MUST use the following prefix for keys: `vendor.package.`
     *
     * @param string $key The namespaced storage key.
     * @param mixed $value The value to be stored.
     *
     * @return void
     */
    final public function setState($key, $value)
    {
        if ($value === null) {
            unset($this->registry[$key]);
        } else {
            $this->registry[$key] = $value;
        }
    }

    /**
     * Gets information stored bound to the loop.
     *
     * Stored information is package private. Packages MUST NOT retrieve the stored state of other packages. Packages
     * MUST use the following prefix for keys: `vendor.package.`
     *
     * @param string $key The namespaced storage key.
     *
     * @return mixed The previously stored value or `null` if it doesn't exist.
     */
    final public function getState($key)
    {
        return isset($this->registry[$key]) ? $this->registry[$key] : null;
    }

    /**
     * Set a callback to be executed when an error occurs.
     *
     * The callback receives the error as the first and only parameter. The return value of the callback gets ignored.
     * If it can't handle the error, it MUST throw the error. Errors thrown by the callback or during its invocation
     * MUST be thrown into the `run` loop and stop the driver.
     *
     * Subsequent calls to this method will overwrite the previous handler.
     *
     * @param callable(\Throwable|\Exception $error)|null $callback The callback to execute. `null` will clear the
     *     current handler.
     *
     * @return callable(\Throwable|\Exception $error)|null The previous handler, `null` if there was none.
     */
    abstract public function setErrorHandler(callable $callback = null);

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
    abstract public function getInfo();

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
    abstract public function getHandle();
}
