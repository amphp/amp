<?php

namespace Amp;

interface Reactor {
    const ALL = 'all';
    const ANY = 'any';
    const SOME = 'some';
    const PAUSE = 'pause';
    const BIND = 'bind';
    const IMMEDIATELY = 'immediately';
    const ONCE = 'once';
    const REPEAT = 'repeat';
    const ON_READABLE = 'onreadable';
    const ON_WRITABLE = 'onwritable';
    const ENABLE = 'enable';
    const DISABLE = 'disable';
    const CANCEL = 'cancel';
    const NOWAIT = 'nowait';
    const NOWAIT_PREFIX = '@';
    const ASYNC = 'async';
    const COROUTINE = 'coroutine';
    const CORETURN = 'return';

    /**
     * Start the event reactor and assume program flow control
     *
     * @param callable $onStart Optional callback to invoke immediately upon reactor start
     */
    public function run(callable $onStart = null);

    /**
     * Execute a single event loop iteration
     *
     * @param bool $noWait If TRUE, return immediately when no watchers are immediately ready to trigger
     */
    public function tick($noWait = false);

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
     * @param callable $callback Any valid PHP callable
     * @param mixed[int|string] $unixTimeOrStr A future unix timestamp or string parsable by strtotime()
     * @throws \InvalidArgumentException On invalid future time
     */
    public function at(callable $callback, $unixTimeOrStr);

    /**
     * Watch a stream resource for readable data and trigger the callback when actionable
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

    /**
     * Resolve the specified generator
     *
     * Upon resolution the final yielded value is used to succeed the returned promise. If an
     * error occurs the returned promise is failed appropriately.
     *
     * @param \Generator $generator
     * @return Promise
     */
    public function coroutine(\Generator $generator);

    /**
     * An optional "last-chance" exception handler for errors resulting during callback invocation
     *
     * If a reactor callback throws and no onError() callback is specified the exception will
     * bubble up the stack. onError() callbacks are passed a single parameter: the uncaught
     * exception that resulted in the callback's invocation.
     *
     * @param callable $onErrorCallback
     */
    public function onError(callable $onErrorCallback);
}
