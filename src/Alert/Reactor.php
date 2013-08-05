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
     * @param callable $callback Any valid PHP callable
     */
    function immediately(callable $callback);

    /**
     * Schedule a callback to execute once
     *
     * @param callable $callback Any valid PHP callable
     * @param float $delay The delay in seconds before the callback will be invoked (zero is allowed)
     */
    function once(callable $callback, $delay);

    /**
     * Schedule a recurring callback
     *
     * @param callable $callback Any valid PHP callable
     * @param float $interval The interval in seconds to observe between callback executions (zero is allowed)
     */
    function schedule(callable $callback, $interval);

    /**
     * Watch a stream resource for IO readable data and trigger the callback when actionable
     *
     * @param resource $stream A stream resource to watch for readable data
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     */
    function onReadable($stream, callable $callback, $enableNow = TRUE);

    /**
     * Watch a stream resource to become writable and trigger the callback when actionable
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
