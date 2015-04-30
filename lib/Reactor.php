<?php

namespace Amp;

interface Reactor {
    /**
     * Start the event reactor and assume program flow control
     *
     * @param callable $onStart An optional callback to invoke upon event loop start
     * @return void
     */
    public function run(callable $onStart = null);

    /**
     * Execute a single event loop iteration
     *
     * @param bool $noWait Should tick return immediately if no watchers are ready to trigger?
     * @return void
     */
    public function tick($noWait = false);

    /**
     * Stop the event reactor
     *
     * @return void
     */
    public function stop();

    /**
     * Schedule a callback for immediate invocation in the next event loop iteration
     *
     * @param callable $callback A callback to invoke in the next iteration of the event loop
     * @param array $options Watcher options
     * @return string Returns unique (to the process) string watcher ID
     */
    public function immediately(callable $callback, array $options = []);

    /**
     * Schedule a callback to execute once
     *
     * @param callable $callback A callback to invoke after the specified millisecond delay
     * @param int $msDelay the number of milliseconds to wait before invoking $callback
     * @param array $options Watcher options
     * @return string Returns unique (to the process) string watcher ID
     */
    public function once(callable $callback, $msDelay, array $options = []);

    /**
     * Schedule a recurring callback to execute every $interval seconds until cancelled
     *
     * @param callable $callback A callback to invoke at the $msDelay interval until cancelled
     * @param int $msInterval The interval at which to repeat $callback invocations
     * @param array $options Watcher options
     * @return string Returns unique (to the process) string watcher ID
     */
    public function repeat(callable $callback, $msInterval, array $options = []);

    /**
     * Watch a stream resource for readable data and trigger the callback when actionable
     *
     * @param resource $stream The stream resource to watch for readability
     * @param callable $callback A callback to invoke when the stream reports as readable
     * @param array $options Watcher options
     * @return string Returns unique (to the process) string watcher ID
     */
    public function onReadable($stream, callable $callback, array $options = []);

    /**
     * Watch a stream resource to become writable and trigger the callback when actionable
     *
     * @param resource $stream The stream resource to watch for writability
     * @param callable $callback A callback to invoke when the stream reports as writable
     * @param array $options Watcher options
     * @return string Returns unique (to the process) string watcher ID
     */
    public function onWritable($stream, callable $callback, array $options = []);

    /**
     * Cancel an existing timer/stream watcher
     *
     * @param string $watcherId The watcher ID to be canceled
     * @return void
     */
    public function cancel($watcherId);

    /**
     * Temporarily disable (but don't cancel) an existing timer/stream watcher
     *
     * @param string $watcherId The watcher ID to be disabled
     * @return void
     */
    public function disable($watcherId);

    /**
     * Enable a disabled timer/stream watcher
     *
     * @param string $watcherId The watcher ID to be enabled
     * @return void
     */
    public function enable($watcherId);

    /**
     * An optional "last-chance" exception handler for errors resulting during callback invocation
     *
     * If an application throws inside the event loop and no onError callback is specified the
     * exception bubbles up and the event loop is stopped. This is undesirable in long-running
     * applications (like servers) where stopping the event loop for an application error is
     * problematic. Amp applications can instead specify the onError callback to handle uncaught
     * exceptions without stopping the event loop.
     *
     * Additionally, generator callbacks which are auto-resolved by the event reactor may fail.
     * Coroutine resolution failures are treated like uncaught exceptions and stop the event reactor
     * if no onError callback is specified to handle these situations.
     *
     * onError callback functions are passed a single parameter: the uncaught exception.
     *
     * @param callable $callback A callback to invoke when an exception occurs inside the event loop
     * @return void
     */
    public function onError(callable $callback);
}
