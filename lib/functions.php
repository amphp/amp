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
function trap(int|array $signals, bool $reference = true, ?CancellationToken $token = null): int
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
