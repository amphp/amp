<?php

namespace Amp\Future;

use Amp\Cancellation;
use Amp\CompositeException;
use Amp\Future;

/**
 * Unwraps the first completed future.
 *
 * If you want the first future completed without an error, use {@see any()} instead.
 *
 * @template T
 *
 * @param iterable<Future<T>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return T
 *
 * @throws \Error If $futures is empty.
 */
function awaitFirst(iterable $futures, ?Cancellation $cancellation = null): mixed
{
    foreach (Future::iterate($futures, $cancellation) as $first) {
        return $first->await();
    }

    throw new \Error('No future provided');
}

/**
 * Unwraps the first completed future.
 *
 * If you want the first future completed without an error, use {@see any()} instead.
 *
 * @template T
 *
 * @param iterable<Future<T>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return T
 *
 * @throws \Error If $futures is empty.
 *
 * @deprecated Use {@see awaitFirst()} instead.
 */
function race(iterable $futures, ?Cancellation $cancellation = null): mixed
{
    return awaitFirst($futures, $cancellation);
}

/**
 * Unwraps the first successfully completed future.
 *
 * If you want the first future completed, successful or not, use {@see awaitFirst()} instead.
 *
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return Tv
 *
 * @throws CompositeException If all futures errored.
 */
function awaitAny(iterable $futures, ?Cancellation $cancellation = null): mixed
{
    $result = awaitN($futures, 1, $cancellation);
    return $result[\array_key_first($result)];
}

/**
 * Unwraps the first successfully completed future.
 *
 * If you want the first future completed, successful or not, use {@see awaitFirst()} instead.
 *
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return Tv
 *
 * @throws CompositeException If all futures errored.
 *
 * @deprecated Use {@see awaitFirst()} instead.
 */
function any(iterable $futures, ?Cancellation $cancellation = null): mixed
{
    return awaitAny($futures, $cancellation);
}

/**
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return non-empty-array<Tk, Tv>
 *
 * @throws CompositeException If all futures errored.
 */
function awaitN(iterable $futures, int $count, ?Cancellation $cancellation = null): array
{
    if ($count <= 0) {
        throw new \ValueError('The count must be greater than 0, got ' . $count);
    }

    $values = [];
    $errors = [];

    foreach (Future::iterate($futures, $cancellation) as $index => $future) {
        try {
            $values[$index] = $future->await();
            if (\count($values) === $count) {
                return $values;
            }
        } catch (\Throwable $throwable) {
            $errors[$index] = $throwable;
        }
    }

    if (\count($values) + \count($errors) < $count) {
        throw new \Error('Iterable did provide enough futures to satisfy the required count of ' . $count);
    }

    /**
     * @var non-empty-array<Tk, \Throwable> $errors
     */
    throw new CompositeException($errors);
}

/**
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return non-empty-array<Tk, Tv>
 *
 * @throws CompositeException If all futures errored.
 *
 * @deprecated Use {@see awaitN()} instead.
 */
function some(iterable $futures, int $count, ?Cancellation $cancellation = null): array
{
    return awaitN($futures, $count, $cancellation);
}

/**
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return array{array<Tk, \Throwable>, array<Tk, Tv>}
 */
function awaitAll(iterable $futures, ?Cancellation $cancellation = null): array
{
    $values = [];
    $errors = [];

    foreach (Future::iterate($futures, $cancellation) as $index => $future) {
        try {
            $values[$index] = $future->await();
        } catch (\Throwable $throwable) {
            $errors[$index] = $throwable;
        }
    }

    return [$errors, $values];
}

/**
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return array{array<Tk, \Throwable>, array<Tk, Tv>}
 *
 * @deprecated Use {@see awaitAll()} instead.
 */
function settle(iterable $futures, ?Cancellation $cancellation = null): array
{
    return awaitAll($futures, $cancellation);
}

/**
 * Awaits all futures to complete or aborts if any errors. The returned array keys will be in the order the futures
 * resolved, not in the order given by the iterable. Sort the array after resolution if necessary.
 *
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return array<Tk, Tv> Unwrapped values with the order preserved.
 */
function await(iterable $futures, ?Cancellation $cancellation = null): array
{
    $values = [];

    // Future::iterate() to throw the first error based on completion order instead of argument order
    foreach (Future::iterate($futures, $cancellation) as $index => $future) {
        $values[$index] = $future->await();
    }

    /** @var array<Tk, Tv> */
    return $values;
}

/**
 * Awaits all futures to complete or aborts if any errors. The returned array keys will be in the order the futures
 * resolved, not in the order given by the iterable. Sort the array after resolution if necessary.
 *
 * @template Tk of array-key
 * @template Tv
 *
 * @param iterable<Tk, Future<Tv>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return array<Tk, Tv> Unwrapped values with the order preserved.
 *
 * @deprecated Use {@see await()} instead.
 */
function all(iterable $futures, ?Cancellation $cancellation = null): array
{
    return await($futures, $cancellation);
}
