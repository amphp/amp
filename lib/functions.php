<?php

namespace Amp;

use Revolt\EventLoop\Loop;
use Revolt\EventLoop\UnsupportedFeatureException;

/**
 * Creates a new fiber asynchronously using the given callable, returning a Future that is completed with the
 * eventual return value of the passed function or will fail if the callback throws an exception.
 *
 * @template T
 *
 * @param callable():T $callback
 *
 * @return Future<T>
 */
function coroutine(callable $callback): Future
{
    $state = new Internal\FutureState;

    $fiber = new \Fiber('Amp\\Internal\\run');
    Loop::queue([$fiber, 'start'], $state, $callback);

    return new Future($state);
}

/**
 * Non-blocking sleep for the specified number of seconds.
 *
 * @param float $timeout Number of seconds to wait.
 * @param bool $reference If false, unreference the underlying watcher.
 * @param CancellationToken|null $token Cancel waiting if cancellation is requested.
 */
function delay(float $timeout, bool $reference = true, ?CancellationToken $token = null): void
{
    $suspension = Loop::createSuspension();
    $watcher = Loop::delay($timeout, static fn () => $suspension->resume(null));
    $id = $token?->subscribe(static fn (CancelledException $exception) => $suspension->throw($exception));

    if (!$reference) {
        Loop::unreference($watcher);
    }

    try {
        $suspension->suspend();
    } finally {
        $token?->unsubscribe($id);
        Loop::cancel($watcher);
    }
}

/**
 * Wait for signal(s) in a non-blocking way.
 *
 * @param int|array $signals Signal number or array of signal numbers.
 * @param bool $reference If false, unreference the underlying watcher.
 * @param CancellationToken|null $token Cancel waiting if cancellation is requested.
 *
 * @return int Caught signal number.
 * @throws UnsupportedFeatureException
 */
function trapSignal(int|array $signals, bool $reference = true, ?CancellationToken $token = null): int
{
    $suspension = Loop::createSuspension();
    $callback = static fn (string $watcher, int $signo) => $suspension->resume($signo);
    $id = $token?->subscribe(static fn (CancelledException $exception) => $suspension->throw($exception));

    $watchers = [];

    if (\is_int($signals)) {
        $signals = [$signals];
    }

    foreach ($signals as $signo) {
        $watchers[] = $watcher = Loop::onSignal($signo, $callback);
        if (!$reference) {
            Loop::unreference($watcher);
        }
    }

    try {
        return $suspension->suspend();
    } finally {
        $token?->unsubscribe($id);
        foreach ($watchers as $watcher) {
            Loop::cancel($watcher);
        }
    }
}

/**
 * Returns a callable that maintains a weak reference to any $this object held by the callable.
 * This allows a class to hold a self-referencing callback without creating a circular reference that would
 * prevent or delay automatic garbage collection.
 * Invoking the returned callback after the object is destroyed will throw an instance of Error.
 *
 * @param callable $callable
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

        if (!\is_array($callable)) {
            throw new \RuntimeException('Unhandled callable type: ' . \gettype($callable));
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

    try {
        $reflection = new \ReflectionFunction($callable);
        $that = $reflection->getClosureThis();
        if (!$that) {
            return $callable;
        }

        $method = $reflection->getShortName();
        if ($method !== '{closure}') {
            // Closure from first-class callable or \Closure::fromCallable(), declare an anonymous closure to rebind.
            $callable = fn (mixed ...$args) => $this->{$method}(...$args);
        }

        // Rebind to remove reference to $that
        $callable = $callable->bindTo(new \stdClass(), $that);
    } catch (\ReflectionException $exception) {
        throw new \RuntimeException('Could not reflect callable', 0, $exception);
    }

    $reference = \WeakReference::create($that);
    return static function (mixed ...$args) use ($reference, $callable): mixed {
        $that = $reference->get();
        if (!$that) {
            throw new \Error('Weakened callback invoked after referenced object destroyed');
        }

        return $callable->call($that, ...$args);
    };
}
