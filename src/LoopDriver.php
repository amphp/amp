<?php

namespace Interop\Async;

interface LoopDriver
{
    /**
     * Start the event loop.
     *
     * @return void
     */
    public function run();

    /**
     * Stop the event loop.
     *
     * @return void
     */
    public function stop();

    /**
     * Defer the execution of a callback.
     *
     * @param callable(string $watcherId, mixed $data) $callback The callback to defer.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public function defer(callable $callback, $data = null);

    /**
     * Delay the execution of a callback. The time delay is approximate and accuracy is not guaranteed.
     *
     * @param int $delay The amount of time, in milliseconds, to delay the execution for.
     * @param callable(string $watcherId, mixed $data) $callback The callback to delay.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public function delay($delay, callable $callback, $data = null);

    /**
     * Repeatedly execute a callback. The interval between executions is approximate and accuracy is not guaranteed.
     *
     * @param int $interval The time interval, in milliseconds, to wait between executions.
     * @param callable(string $watcherId, mixed $data) $callback The callback to repeat.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public function repeat($interval, callable $callback, $data = null);

    /**
     * Execute a callback when a stream resource becomes readable.
     *
     * @param resource $stream The stream to monitor.
     * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public function onReadable($stream, callable $callback, $data = null);

    /**
     * Execute a callback when a stream resource becomes writable.
     *
     * @param resource $stream The stream to monitor.
     * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public function onWritable($stream, callable $callback, $data = null);

    /**
     * Execute a callback when a signal is received.
     *
     * @param int $signo The signal number to monitor.
     * @param callable(string $watcherId, int $signo, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public function onSignal($signo, callable $callback, $data = null);

    /**
     * Enable a watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public function enable($watcherId);

    /**
     * Disable a watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public function disable($watcherId);

    /**
     * Cancel a watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public function cancel($watcherId);

    /**
     * Reference a watcher.
     *
     * This will keep the event loop alive whilst the event is still being monitored. Events have this state by default.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public function reference($watcherId);

    /**
     * Unreference a watcher.
     *
     * The event loop should exit the run method when only unreferenced events are still being monitored. Events are all
     * referenced by default.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public function unreference($watcherId);

    /**
     * Set a callback to be executed when an error occurs.
     *
     * Subsequent calls to this method will overwrite the previous handler.
     *
     * @param callable(\Throwable|\Exception $error)|null $callback The callback to execute; null will clear the current handler.
     *
     * @return void
     */
    public function setErrorHandler(callable $callback = null);

    /**
     * Retrieve an associative array of information about the event loop driver.
     *
     * The returned array MUST contain the following data describing the driver's
     * currently registered watchers:
     *
     *  [
     *      "defer"         => ["enabled" => int, "disabled" => int],
     *      "delay"         => ["enabled" => int, "disabled" => int],
     *      "repeat"        => ["enabled" => int, "disabled" => int],
     *      "on_readable"   => ["enabled" => int, "disabled" => int],
     *      "on_writable"   => ["enabled" => int, "disabled" => int],
     *      "on_signal"     => ["enabled" => int, "disabled" => int],
     *      "keep_alive"    => int,
     *  ];
     *
     * keep_alive refers to the number of referenced watchers.
     *
     * Implementations MAY optionally add more information in the array but
     * at minimum the above key => value format MUST always be provided.
     *
     * @return array
     */
    public function info();

    /**
     * Get the underlying loop handle.
     *
     * Example: the uv_loop resource for libuv or the EvLoop object for libev or null for a native driver
     *
     * Note: This function is *not* exposed in the Loop class; users shall access it directly on the respective loop instance.
     *
     * @return null|object|resource The loop handle the event loop operates on. Null if there is none.
     */
    public function getHandle();
}
