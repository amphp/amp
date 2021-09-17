<?php

namespace Amp\Future;

use Amp\CancellationToken;
use Amp\CompositeException;
use Amp\Future;
use Amp\Internal;
use Revolt\EventLoop\Loop;

/**
 * Spawns a new fiber asynchronously using the given callable and argument list.
 *
 * @template T
 *
 * @param callable():T $callback
 *
 * @return Future<T>
 */
function spawn(callable $callback): Future
{
    $state = new Internal\FutureState;

    $fiber = new \Fiber('Amp\\Internal\\run');
    Loop::queue([$fiber, 'start'], $state, $callback);

    return new Future($state);
}

/**
 * Unwraps the first completed future.
 *
 * If you want the first future completed without an error, use {@see any()} instead.
 *
 * @template T
 *
 * @param iterable<Future<T>> $futures
 * @param CancellationToken|null $token Optional cancellation token.
 *
 * @return T
 *
 * @throws \Error If $futures is empty.
 */
function first(iterable $futures, ?CancellationToken $token = null): mixed
{
    foreach (Future::iterate($futures, $token) as $first) {
        return $first->join();
    }

    throw new \Error('No future provided');
}

/**
 * Unwraps the first successfully completed future.
 *
 * If you want the first future completed, successful or not, use {@see first()} instead.
 *
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param CancellationToken|null $token Optional cancellation token.
 *
 * @return Tv
 *
 * @throws CompositeException If all futures errored.
 */
function any(iterable $futures, ?CancellationToken $token = null): mixed
{
    $result = some($futures, 1, $token);
    return $result[\array_key_first($result)];
}

/**
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param CancellationToken|null $token Optional cancellation token.
 *
 * @return array<Tk, Tv>
 *
 * @throws CompositeException If all futures errored.
 */
function some(iterable $futures, int $count, ?CancellationToken $token = null): array
{
    if ($count <= 0) {
        throw new \ValueError('The count must be greater than 0');
    }

    $values = [];
    $errors = [];
    foreach (Future::iterate($futures, $token) as $index => $future) {
        try {
            $values[$index] = $future->join();
            if (\count($values) === $count) {
                return $values;
            }
        } catch (\Throwable $throwable) {
            $errors[$index] = $throwable;
        }
    }

    if (empty($errors)) {
        throw new \Error('Iterable did provide enough futures to satisfy the required count');
    }

    /**
     * @var non-empty-array<Tk, \Throwable> $errors
     */
    throw new CompositeException($errors);
}

/**
 * Awaits all futures to complete or aborts if any errors.
 *
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param CancellationToken|null $token Optional cancellation token.
 *
 * @return array<Tk, Tv> Unwrapped values with the order preserved.
 */
function all(iterable $futures, CancellationToken $token = null): array
{
    $futures = \is_array($futures) ? $futures : \iterator_to_array($futures);

    // Future::iterate() to throw the first error based on completion order instead of argument order
    foreach (Future::iterate($futures, $token) as $k => $future) {
        $futures[$k] = $future->join();
    }

    /** @var array<Tk, Tv> */
    return $futures;
}
