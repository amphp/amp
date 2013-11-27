<?php

namespace Alert;

interface Reactor {

    /**
     * Start the event reactor and assume program flow control
     */
    function run();

    /**
     * Execute a single event loop iteration
     */
    function tick();

    /**
     * Stop the event reactor
     */
    function stop();

    /**
     * Schedule a callback for immediate invocation in the next event loop iteration
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param callable $callback Any valid PHP callable
     */
    function immediately(callable $callback);

    /**
     * Schedule a callback to execute once
     *
     * Time intervals are measured in seconds. Floating point values < 0 denote intervals less than
     * one second. e.g. $interval = 0.001 means a one millisecond interval.
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param callable $callback Any valid PHP callable
     * @param float $delay The delay in seconds before the callback will be invoked (zero is allowed)
     */
    function once(callable $callback, $delay);

    /**
     * Schedule a recurring callback to execute every $interval seconds until cancelled
     *
     * Time intervals are measured in seconds. Floating point values < 0 denote intervals less than
     * one second. e.g. $interval = 0.001 means a one millisecond interval.
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param callable $callback Any valid PHP callable
     * @param float $interval The interval in seconds to observe between callback executions (zero is allowed)
     */
    function repeat(callable $callback, $interval);

    /**
     * Schedule an event to trigger once at the specified time
     *
     * Though it can't be enforced at the interface level all timer/stream scheduling methods
     * should return a unique integer identifying the relevant watcher.
     *
     * @param callable $callback Any valid PHP callable
     * @param string $timeString Any string that can be parsed by strtotime() and is in the future
     */
    function at(callable $callback, $timeString);

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
    function onReadable($stream, callable $callback, $enableNow = TRUE);

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
    function onWritable($stream, callable $callback, $enableNow = TRUE);

    /**
     * Cancel an existing timer/stream watcher
     *
     * @param int $watcherId
     */
    function cancel($watcherId);

    /**
     * Temporarily disable (but don't cancel) an existing timer/stream watcher
     *
     * @param int $watcherId
     */
    function disable($watcherId);

    /**
     * Enable a disabled timer/stream watcher
     *
     * @param int $watcherId
     */
    function enable($watcherId);

}
