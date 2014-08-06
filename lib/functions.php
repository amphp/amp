<?php

namespace Alert;

/**
 * Schedule a callback for immediate invocation in the next event loop iteration
 *
 * Watchers registered using this function will be automatically garbage collected after execution.
 *
 * @param callable $func Any valid PHP callable
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function immediately(callable $func) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    return $reactor->select()->immediately($func);
}

/**
 * Schedule a callback to execute once
 *
 * Watchers registered using this function will be automatically garbage collected after execution.
 *
 * @param callable $func Any valid PHP callable
 * @param int $msDelay The delay in milliseconds before the callback will trigger (may be zero)
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function once(callable $func, $msDelay) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    return $reactor->once($func, $msDelay);
}

/**
 * Schedule a recurring callback to execute every $interval seconds until cancelled
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 *
 * @param callable $func Any valid PHP callable
 * @param int $msDelay The delay in milliseconds in-between callback invocations (may be zero)
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function repeat(callable $func, $msDelay) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    return $reactor->repeat($func, $msDelay);
}

/**
 * Schedule an event to trigger once at the specified time
 *
 * Watchers registered using this function will be automatically garbage collected after execution.
 *
 * @param callable $func Any valid PHP callable
 * @param string $timeString Any string that can be parsed by strtotime() and is in the future
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function at(callable $func, $timeString) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    return $reactor->at($func, $timeString);
}

/**
 * Enable a disabled timer or stream IO watcher
 *
 * Calling enable() on an already-enabled watcher will have no effect.
 *
 * @param int $watcherId
 * @return void
 */
function enable($watcherId) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    $reactor->enable($watcherId);
}

/**
 * Temporarily disable (but don't cancel) an existing timer/stream watcher
 *
 * Calling disable() on a nonexistent or previously-disabled watcher will have no effect.
 *
 * NOTE: Disabling a repeating or stream watcher is not sufficient to free associated resources.
 * When the watcher is no longer needed applications must still use cancel() to clear related
 * memory and avoid leaks.
 *
 * @param int $watcherId
 * @return void
 */
function disable($watcherId) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    $reactor->disable($watcherId);
}

/**
 * Cancel an existing timer/stream watcher
 *
 * Calling cancel() on a non-existent watcher will have no effect.
 *
 * @param int $watcherId
 * @return void
 */
function cancel($watcherId) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    $reactor->cancel($watcherId);
}

/**
 * Watch a stream IO resource for readable data and trigger the specified callback when actionable
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 *
 * @param resource $stream A stream resource to watch for readable data
 * @param callable $func Any valid PHP callable
 * @param bool $enableNow Should the watcher be enabled now or held for later use?
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function onReadable($stream, callable $func, $enableNow = true) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    return $reactor->onReadable($stream, $func, $enableNow);
}

/**
 * Watch a stream IO resource for writability and trigger the specified callback when actionable
 *
 * NOTE: Sockets are essentially "always writable" (as long as their write buffer is not full).
 * Therefore, it's critical that applications disable or cancel write watchers as soon as all data
 * is written or the watcher will trigger endlessly and hammer the CPU.
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 *
 * @param resource $stream A stream resource to watch for writable data
 * @param callable $func Any valid PHP callable
 * @param bool $enableNow Should the watcher be enabled now or held for later use?
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function onWritable($stream, callable $func, $enableNow = true) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    return $reactor->onWritable($stream, $func, $enableNow);
}

/**
 * Similar to onReadable/onWritable but uses a flag bitmask for extended option assignment
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 *
 * @param resource $stream A stream resource to watch for IO capability
 * @param int $flags Option bitmask (Reactor::WATCH_READ, Reactor::WATCH_WRITE, etc)
 * @param callable $func Any valid PHP callable
 */
function watchStream($stream, $flags, callable $func) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    return $reactor->watchStream($stream, $flags, $func);
}

/**
 * Execute a single event loop iteration
 *
 * @return void
 */
function tick() {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    $reactor->tick();
}

/**
 * Start the event reactor and assume program flow control
 *
 * @param callable $onStart Optional callback to invoke immediately upon reactor start
 * @return void
 */
function run(callable $onStart = null) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    $reactor->run($onStart);
}

/**
 * Stop the event reactor
 *
 * @return void
 */
function stop() {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    $reactor->stop();
}

/**
 * Get the global event reactor
 *
 * Note that the $factory callable is only invoked if no global reactor has yet been initialized.
 *
 * @param callable $factory Optional factory callable for initializing a reactor
 * @return \Alert\Reactor
 */
function reactor(callable $factory = null) {
    static $reactor;
    return ($reactor = $reactor ?: ReactorFactory::select($factory));
}
