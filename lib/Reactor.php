<?php

namespace Alert;

interface Reactor {
    const POLL_READ = 1;
    const POLL_WRITE = 2;
    const POLL_SOCK = 4;
    const ENABLE_NOW = 8;

    /**
     * Start the event reactor and assume program flow control
     *
     * @param callable $onStart Optional callback to invoke immediately upon reactor start
     */
    public function run(callable $onStart = null);

    /**
     * Execute a single event loop iteration
     */
    public function tick();

    /**
     * Stop the event reactor
     */
    public function stop();

    /**
     * Schedule a callback for immediate invocation in the next event loop iteration
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param callable $callback Any valid PHP callable
     */
    public function immediately(callable $callback);

    /**
     * Schedule a callback to execute once
     *
     * Time intervals are measured in milliseconds.
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msDelay The delay in milliseconds before the callback will trigger (may be zero)
     */
    public function once(callable $callback, $msDelay);

    /**
     * Schedule a recurring callback to execute every $interval seconds until cancelled
     *
     * Time intervals are measured in milliseconds.
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msDelay The delay in milliseconds before the callback will trigger (may be zero)
     */
    public function repeat(callable $callback, $msDelay);

    /**
     * Schedule an event to trigger once at the specified time
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param callable $callback Any valid PHP callable
     * @param string $timeString Any string that can be parsed by strtotime() and is in the future
     */
    public function at(callable $callback, $timeString);

    /**
     * Watch a stream resource for IO readable data and trigger the callback when actionable
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param resource $stream A stream resource to watch for readable data
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     */
    public function onReadable($stream, callable $callback, $enableNow = true);

    /**
     * Watch a stream resource to become writable and trigger the callback when actionable
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param resource $stream A stream resource to watch for writability
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     */
    public function onWritable($stream, callable $callback, $enableNow = true);

    /**
     * Similar to onReadable/onWritable but uses a flag bitmask for extended option assignment
     *
     * @param resource $stream A stream resource to watch for writability
     * @param callable $callback Any valid PHP callable
     * @param int $flags Option bitmask (Reactor::POLL_READ, Reactor::POLL_WRITE, etc)
     */
    public function watchStream($stream, $flags, callable $callback);

    /**
     * Cancel an existing timer/stream watcher
     *
     * @param int $watcherId
     */
    public function cancel($watcherId);

    /**
     * Temporarily disable (but don't cancel) an existing timer/stream watcher
     *
     * @param int $watcherId
     */
    public function disable($watcherId);

    /**
     * Enable a disabled timer/stream watcher
     *
     * @param int $watcherId
     */
    public function enable($watcherId);
}
