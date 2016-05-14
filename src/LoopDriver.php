<?php

namespace Interop\Async\EventLoop;

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
     * @param callable(mixed $data, string $watcherIdentifier) $callback The callback to defer.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function defer(callable $callback, $data = null);

    /**
     * Delay the execution of a callback. The time delay is approximate and accuracy is not guaranteed.
     *
     * @param callable(mixed $data, string $watcherIdentifier) $callback The callback to delay.
     * @param int $delay The amount of time, in milliseconds, to delay the execution for.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function delay(callable $callback, $delay, $data = null);

    /**
     * Repeatedly execute a callback. The interval between executions is approximate and accuracy is not guaranteed.
     *
     * @param callable(mixed $data, string $watcherIdentifier) $callback The callback to repeat.
     * @param int $interval The time interval, in milliseconds, to wait between executions.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function repeat(callable $callback, $interval, $data = null);

    /**
     * Execute a callback when a stream resource becomes readable.
     *
     * @param resource $stream The stream to monitor.
     * @param callable(resource $stream, mixed $data, string $watcherIdentifier) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function onReadable($stream, callable $callback, $data = null);

    /**
     * Execute a callback when a stream resource becomes writable.
     *
     * @param resource $stream The stream to monitor.
     * @param callable(resource $stream, mixed $data, string $watcherIdentifier) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function onWritable($stream, callable $callback, $data = null);

    /**
     * Execute a callback when a signal is received.
     *
     * @param int $signo The signal number to monitor.
     * @param callable(int $signo, mixed $data, string $watcherIdentifier) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public function onSignal($signo, callable $callback, $data = null);

    /**
     * Execute a callback when an error occurs.
     *
     * @param callable(\Throwable|\Exception $exception) $callback The callback to execute.
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
}
