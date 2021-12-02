<?php

namespace Amp;

use Amp\Internal\FutureState;
use Revolt\EventLoop;
use Revolt\EventLoop\UnsupportedFeatureException;

/**
 * Creates a new fiber asynchronously using the given closure, returning a Future that is completed with the
 * eventual return value of the passed function or will fail if the closure throws an exception.
 *
 * @template T
 *
 * @param \Closure():T $closure
 *
 * @return Future<T>
 */
function async(\Closure $closure): Future
{
    static $run = null;

    $run ??= static function (FutureState $state, callable $callback) {
        try {
            $state->complete($callback());
        } catch (\Throwable $exception) {
            $state->error($exception);
        }
    };

    $state = new Internal\FutureState;

    EventLoop::queue($run, $state, $closure);

    return new Future($state);
}

/**
 * Returns the current time relative to an arbitrary point in time.
 *
 * @return float Time in seconds.
 */
function now(): float
{
    return (float) \hrtime(true) / 1_000_000_000;
}

/**
 * Non-blocking sleep for the specified number of seconds.
 *
 * @param float             $timeout Number of seconds to wait.
 * @param bool              $reference If false, unreference the underlying watcher.
 * @param Cancellation|null $cancellation Cancel waiting if cancellation is requested.
 */
function delay(float $timeout, bool $reference = true, ?Cancellation $cancellation = null): void
{
    $suspension = EventLoop::createSuspension();
    $callbackId = EventLoop::delay($timeout, static fn () => $suspension->resume());
    $cancellationId = $cancellation?->subscribe(
        static fn (CancelledException $exception) => $suspension->throw($exception)
    );

    if (!$reference) {
        EventLoop::unreference($callbackId);
    }

    try {
        $suspension->suspend();
    } finally {
        EventLoop::cancel($callbackId);

        /** @psalm-suppress PossiblyNullArgument $cancellationId will not be null if $cancellation is not null. */
        $cancellation?->unsubscribe($cancellationId);
    }
}

/**
 * Wait for signal(s) in a non-blocking way.
 *
 * @param int|array         $signals Signal number or array of signal numbers.
 * @param bool              $reference If false, unreference the underlying watcher.
 * @param Cancellation|null $cancellation Cancel waiting if cancellation is requested.
 *
 * @return int Caught signal number.
 * @throws UnsupportedFeatureException
 */
function trapSignal(int|array $signals, bool $reference = true, ?Cancellation $cancellation = null): int
{
    $suspension = EventLoop::createSuspension();
    $callback = static fn (string $watcher, int $signal) => $suspension->resume($signal);
    $id = $cancellation?->subscribe(static fn (CancelledException $exception) => $suspension->throw($exception));

    $callbackIds = [];

    if (\is_int($signals)) {
        $signals = [$signals];
    }

    foreach ($signals as $signo) {
        $callbackIds[] = $callbackId = EventLoop::onSignal($signo, $callback);
        if (!$reference) {
            EventLoop::unreference($callbackId);
        }
    }

    try {
        return $suspension->suspend();
    } finally {
        foreach ($callbackIds as $callbackId) {
            EventLoop::cancel($callbackId);
        }

        /** @psalm-suppress PossiblyNullArgument $id will not be null if $cancellation is not null. */
        $cancellation?->unsubscribe($id);
    }
}

/**
 * Returns a callable that maintains a weak reference to any $this object held by the callable.
 * This allows a class to hold a self-referencing callback without creating a circular reference that would
 * prevent or delay automatic garbage collection.
 * Invoking the returned callback after the object is destroyed will throw an instance of Error.
 *
 * @param callable $callable
 *
 * @return callable
 */
function weaken(callable $callable): callable
{
    if (!$callable instanceof \Closure) {
        if (\is_string($callable)) {
            return $callable;
        }

        if (\is_object($callable)) {
            $callable = [$callable, '__invoke'];
        }

        [$that, $method] = $callable;
        if (!\is_object($that)) {
            return $callable;
        }

        $reference = \WeakReference::create($that);
        return static function (mixed ...$args) use ($reference, $method): mixed {
            $that = $reference->get();
            if (!$that) {
                throw new \Error('Weakened callback invoked after referenced object destroyed');
            }

            return $that->{$method}(...$args);
        };
    }

    $reflection = new \ReflectionFunction($callable);

    $that = $reflection->getClosureThis();
    if (!$that) {
        return $callable;
    }

    $method = $reflection->getShortName();
    if ($method !== '{closure}') {
        // Closure from first-class callable or \Closure::fromCallable(), declare an anonymous closure to rebind.
        /** @psalm-suppress InvalidScope Closure is bound before being invoked. */
        $callable = fn (mixed ...$args) => $this->{$method}(...$args);
    } else {
        // Rebind to remove reference to $that
        $callable = $callable->bindTo(new \stdClass());
    }

    // For internal classes use \Closure::bindTo() without scope.
    $useBindTo = !(new \ReflectionClass($that))->isUserDefined();

    $reference = \WeakReference::create($that);
    return static function (mixed ...$args) use ($reference, $callable, $useBindTo): mixed {
        $that = $reference->get();
        if (!$that) {
            throw new \Error('Weakened callback invoked after referenced object destroyed');
        }

        if ($useBindTo) {
            $callable = $callable->bindTo($that);

            if (!$callable) {
                throw new \RuntimeException('Unable to rebind function to object of type ' . \get_class($that));
            }

            return $callable(...$args);
        }

        return $callable->call($that, ...$args);
    };
}
