<?php

namespace Amp;

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
    return $reactor->immediately($func);
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
 * @param callable $func Any valid PHP callable
 * @param int $flags Option bitmask (Reactor::WATCH_READ, Reactor::WATCH_WRITE, etc)
 */
function watchStream($stream, callable $func, $flags) {
    static $reactor;
    $reactor = $reactor ?: ReactorFactory::select();
    return $reactor->watchStream($stream, $func, $flags);
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
 * React to process control signals
 *
 * @param int $signo The signal number to watch for
 * @param callable $onSignal
 * @throws \RuntimeException if the current environment cannot support signal handling
 * @return int Returns a unique integer watcher ID
 */
function onSignal($signo, callable $onSignal) {
    /**
     * @var $reactor \Amp\SignalReactor
     */
    static $reactor;
    if ($reactor) {
        return $reactor->onSignal($signo, $onSignal);
    } elseif (!($reactor = ReactorFactory::select()) instanceof SignalReactor) {
        throw new \RuntimeException(
            'Your PHP environment does not support signal handling. Please install pecl/libevent or the php-uv extension'
        );
    } else {
        return $reactor->onSignal($signo, $onSignal);
    }
}

/**
 * Get the global event reactor
 *
 * Note that the $factory callable is only invoked if no global reactor has yet been initialized.
 *
 * @param callable $factory Optional factory callable for initializing a reactor
 * @return \Amp\Reactor
 */
function reactor(callable $factory = null) {
    static $reactor;
    return ($reactor = $reactor ?: ReactorFactory::select($factory));
}

/**
 * Get a singleton combinator instance
 *
 * @param callable $factory
 * @return \Amp\Combinator
 */
function combinator(callable $factory = null) {
    static $combinator;
    if ($factory) {
        return $combinator = $factory();
    } elseif ($combinator) {
        return $combinator;
    } else {
        return $combinator = new Combinator(reactor());
    }
}

/**
 * If any one of the Promises fails the resulting Promise will fail. Otherwise
 * the resulting Promise succeeds with an array matching keys from the input array
 * to their resolved values.
 *
 * @param array[\Amp\Promise] $promises
 * @return \Amp\Promise
 */
function all(array $promises) {
    return combinator()->all($promises);
}

/**
 * Resolves with a two-item array delineating successful and failed Promise results.
 *
 * The resulting Promise will only fail if ALL of the Promise values fail or if the
 * Promise array is empty.
 *
 * The resulting Promise is resolved with an indexed two-item array of the following form:
 *
 *     [$arrayOfFailures, $arrayOfSuccesses]
 *
 * The individual keys in the resulting arrays are preserved from the initial Promise array
 * passed to the function for evaluation.
 *
 * @param array[\Amp\Promise] $promises
 * @return \Amp\Promise
 */
function some(array $promises) {
    return combinator()->some($promises);
}

/**
 * Resolves with a two-item array delineating successful and failed Promise results.
 *
 * This function is the same as some() with the notable exception that it will never fail even
 * if all promises in the array resolve unsuccessfully.
 *
 * @param array $promises
 * @return \Amp\Promise
 */
function any(array $promises) {
    return combinator()->any($promises);
}

/**
 * Resolves with the first successful Promise value. The resulting Promise will only fail if all
 * Promise values in the group fail or if the initial Promise array is empty.
 *
 * @param array[\Amp\Promise] $promises
 * @return \Amp\Promise
 */
function first(array $promises) {
    return combinator()->first($promises);
}

/**
 * Map future values using the specified callable
 *
 * @param array $promises
 * @param callable $func
 * @return \Amp\Promise
 */
function map(array $promises, callable $func) {
    return combinator()->map($promises, $func);
}

/**
 * Filter future values using the specified callable
 *
 * If the functor returns a truthy value the resolved promise result is retained, otherwise it is
 * discarded. Array keys are retained for any results not filtered out by the functor.
 *
 * @param array $promises
 * @param callable $func
 * @return \Amp\Promise
 */
function filter(array $promises, callable $func) {
    return combinator()->filter($promises, $func);
}

/**
 * A co-routine to resolve Generators that yield Promise instances
 *
 * Returns a promise that will resolve when the generator completes. The final value yielded by the
 * generator is used to resolve the returned promise.
 *
 * @param \Generator
 * @return \Amp\Promise
 */
function resolve(\Generator $gen) {
    static $resolver;
    if (empty($resolver)) {
        $resolver = new Resolver;
    }

    return $resolver->resolve($gen);
}
