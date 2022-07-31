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
 * @param \Closure(...):T $closure
 * @param mixed ...$args Arguments forwarded to the closure when starting the fiber.
 *
 * @return Future<T>
 */
function async(\Closure $closure, mixed ...$args): Future
{
    static $run = null;

    $run ??= static function (FutureState $state, \Closure $closure, array $args): void {
        $s = $state;
        $c = $closure;
        $a = $args;

        /* Null function arguments so an exception thrown from the closure does not contain the FutureState object
         * in the stack trace, which would create a circular reference, preventing immediate garbage collection */
        $state = $closure = $args = null;

        try {
            // Clear $a to allow garbage collection
            $s->complete($c(...$a, ...($a = [])));
        } catch (\Throwable $exception) {
            $s->error($exception);
        }
    };

    $state = new Internal\FutureState;

    EventLoop::queue($run, $state, $closure, $args);

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
 * @param float $timeout Number of seconds to wait.
 * @param bool $reference If false, unreference the underlying watcher.
 * @param Cancellation|null $cancellation Cancel waiting if cancellation is requested.
 */
function delay(float $timeout, bool $reference = true, ?Cancellation $cancellation = null): void
{
    $suspension = EventLoop::getSuspension();
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
 * @param int|int[] $signals Signal number or array of signal numbers.
 * @param bool $reference If false, unreference the underlying watcher.
 * @param Cancellation|null $cancellation Cancel waiting if cancellation is requested.
 *
 * @return int Caught signal number.
 * @throws UnsupportedFeatureException
 */
function trapSignal(int|array $signals, bool $reference = true, ?Cancellation $cancellation = null): int
{
    $suspension = EventLoop::getSuspension();
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
 * Returns a Closure that maintains a weak reference to any $this object held by the Closure (a weak-Closure).
 * This allows a class to hold a self-referencing Closure without creating a circular reference that would
 * prevent or delay automatic garbage collection.
 * Invoking the returned Closure after the object is destroyed will throw an instance of Error.
 *
 * @template TReturn
 * @template TWeakClosure as \Closure(...):TReturn
 *
 * @param TWeakClosure $closure
 *
 * @return TWeakClosure
 */
function weakClosure(\Closure $closure): \Closure
{
    $reflection = new \ReflectionFunction($closure);

    $that = $reflection->getClosureThis();
    if (!$that) {
        return $closure;
    }

    // For internal classes use \Closure::bindTo() without scope.
    $scope = $reflection->getClosureScopeClass();
    $useBindTo = !$scope || $that::class !== $scope->name || $scope->isInternal();

    $method = $reflection->getShortName();
    if ($method !== '{closure}') {
        // Closure from first-class callable or \Closure::fromCallable(), declare an anonymous closure to rebind.
        /** @psalm-suppress InvalidScope Closure is bound before being invoked. */
        $closure = fn (mixed ...$args): mixed => $this->{$method}(...$args);
        if ($useBindTo && $scope) {
            $closure = $closure->bindTo(null, $scope->name);

            if (!$closure) {
                throw new \RuntimeException('Unable to rebind function to type ' . $scope->name);
            }
        }
    } else {
        // Rebind to remove reference to $that
        $closure = $closure->bindTo(new \stdClass());
    }

    $reference = \WeakReference::create($that);

    /** @var TWeakClosure */
    return static function (mixed ...$args) use ($reference, $closure, $useBindTo): mixed {
        $that = $reference->get();
        if (!$that) {
            throw new \Error('Weakened closure invoked after referenced object destroyed');
        }

        if ($useBindTo) {
            $closure = $closure->bindTo($that);

            if (!$closure) {
                throw new \RuntimeException('Unable to rebind function to object of type ' . \get_class($that));
            }

            return $closure(...$args);
        }

        return $closure->call($that, ...$args);
    };
}
