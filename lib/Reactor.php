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
    public function tick(bool $noWait = false);

    /**
     * Stop the event reactor
     *
     * @return void
     */
    public function stop();

    /**
     * Schedule a callback for immediate invocation in the next event loop iteration
     *
     * @param callable $func A callback to invoke in the next iteration of the event loop
     * @return string Returns unique (to the process) string watcher ID
     */
    public function immediately(callable $func): string;

    /**
     * Schedule a callback to execute once
     *
     * @param callable $func A callback to invoke after the specified millisecond delay
     * @param int $millisecondDelay the number of milliseconds to wait before invoking $func
     * @return string Returns unique (to the process) string watcher ID
     */
    public function once(callable $func, int $millisecondDelay): string;

    /**
     * Schedule a recurring callback to execute every $interval seconds until cancelled
     *
     * @param callable $func A callback to invoke at the $millisecondDelay interval until canceled
     * @param int $millisecondDelay The interval at which to repeat $func invocations
     * @return string Returns unique (to the process) string watcher ID
     */
    public function repeat(callable $func, int $millisecondDelay): string;

    /**
     * Schedule an event to trigger once at the specified time
     *
     * @param callable $func A callback to invoke at the specified future time
     * @param int|string $unixTimeOrStr
     * @return string Returns unique (to the process) string watcher ID
     */
    public function at(callable $func, $unixTimeOrStr): string;

    /**
     * Watch a stream resource for readable data and trigger the callback when actionable
     *
     * @param resource $stream The stream resource to watch for readability
     * @param callable $func A callback to invoke when the stream reports as readable
     * @param bool $enableNow Whether or not the watcher should be enabled upon creation
     * @return string Returns unique (to the process) string watcher ID
     */
    public function onReadable($stream, callable $func, bool $enableNow = true): string;

    /**
     * Watch a stream resource to become writable and trigger the callback when actionable
     *
     * @param resource $stream The stream resource to watch for writability
     * @param callable $func A callback to invoke when the stream reports as writable
     * @param bool $enableNow Whether or not the watcher should be enabled upon creation
     * @return string Returns unique (to the process) string watcher ID
     */
    public function onWritable($stream, callable $func, bool $enableNow = true): string;

    /**
     * Cancel an existing timer/stream watcher
     *
     * @param string $watcherId The watcher ID to be canceled
     * @return void
     */
    public function cancel(string $watcherId);

    /**
     * Temporarily disable (but don't cancel) an existing timer/stream watcher
     *
     * @param string $watcherId The watcher ID to be disabled
     * @return void
     */
    public function disable(string $watcherId);

    /**
     * Enable a disabled timer/stream watcher
     *
     * @param string $watcherId The watcher ID to be enabled
     * @return void
     */
    public function enable(string $watcherId);

    /**
     * An optional "last-chance" exception handler for errors resulting during callback invocation
     *
     * If an application throws inside the event loop and no onError callback is specified the
     * exception bubbles up and the event loop is stopped. This is undesirable in long-running
     * applications (like servers) where stopping the event loop for an application error is
     * undesirable.
     *
     * onError callback functions are passed a single parameter: the uncaught exception.
     *
     * @param callable $func A callback to invoke when an exception occurs inside the event loop
     * @return void
     */
    public function onError(callable $func);
}
