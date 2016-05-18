<?php

namespace Interop\Async;

interface LoopDriver
{
    const FEATURE_SIGNAL_HANDLING = 0b001;

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
     * @param callable $callback The callback to defer.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function defer(callable $callback);

    /**
     * Delay the execution of a callback. The time delay is approximate and accuracy is not guaranteed.
     *
     * @param callable $callback The callback to delay.
     * @param int $delay The amount of time, in milliseconds, to delay the execution for.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function delay(callable $callback, $delay);

    /**
     * Repeatedly execute a callback. The interval between executions is approximate and accuracy is not guaranteed.
     *
     * @param callable $callback The callback to repeat.
     * @param int $interval The time interval, in milliseconds, to wait between executions.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function repeat(callable $callback, $interval);

    /**
     * Execute a callback when a stream resource becomes readable.
     *
     * @param resource $stream The stream to monitor.
     * @param callable $callback The callback to execute.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function onReadable($stream, callable $callback);

    /**
     * Execute a callback when a stream resource becomes writable.
     *
     * @param resource $stream The stream to monitor.
     * @param callable $callback The callback to execute.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function onWritable($stream, callable $callback);

    /**
     * Execute a callback when a signal is received.
     *
     * @param int $signo The signal number to monitor.
     * @param callable $callback The callback to execute.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function onSignal($signo, callable $callback);

    /**
     * Execute a callback when an error occurs.
     *
     * @param callable $callback The callback to execute.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function onError(callable $callback);

    /**
     * Enable an event.
     *
     * @param string $eventIdentifier The event identifier.
     *
     * @return void
     */
    public function enable($eventIdentifier);

    /**
     * Disable an event.
     *
     * @param string $eventIdentifier The event identifier.
     *
     * @return void
     */
    public function disable($eventIdentifier);

    /**
     * Cancel an event.
     *
     * @param string $eventIdentifier The event identifier.
     *
     * @return void
     */
    public function cancel($eventIdentifier);

    /**
     * Reference an event.
     *
     * This will keep the event loop alive whilst the event is still being monitored. Events have this state by default.
     *
     * @param string $eventIdentifier The event identifier.
     *
     * @return void
     */
    public function reference($eventIdentifier);

    /**
     * Unreference an event.
     *
     * The event loop should exit the run method when only unreferenced events are still being monitored. Events are all
     * referenced by default.
     *
     * @param string $eventIdentifier The event identifier.
     *
     * @return void
     */
    public function unreference($eventIdentifier);

    /**
     * Check whether an optional features is supported by this implementation
     * and system.
     *
     * Example: If the implementation can handle signals using PCNTL, but the
     * PCNTL extension is not available, the feature MUST NOT be marked as
     * supported.
     *
     * @param int $feature FEATURE constant
     *
     * @return bool
     */
    public function supports($feature);

    /**
     * Get the underlying loop handle.
     *
     * Example: the uv_loop resource for libuv or the EvLoop object for libev or null for a native driver
     *
     * @return null|object|resource The loop handle the event loop operates on. Null if there is none.
     */
    public static function getLoopHandle();
}
