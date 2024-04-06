<?php declare(strict_types=1);

namespace Amp\Future;

use Amp\Cancellation;
use Amp\CompositeException;
use Amp\CompositeLengthException;
use Amp\Future;

/**
 * Unwraps the first completed future.
 *
 * If you want the first future completed without an error, use {@see awaitAny()} instead.
 *
 * @template T
 *
 * @param iterable<Future<T>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return T
 *
 * @throws CompositeLengthException If {@code $futures} is empty.
 */
function awaitFirst(iterable $futures, ?Cancellation $cancellation = null): mixed
{
    foreach (Future::iterate($futures, $cancellation) as $first) {
        return $first->await();
    }

    throw new CompositeLengthException('Argument #1 ($futures) is empty');
}

/**
 * Awaits the first successfully completed future, ignoring errors.
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
 * @throws CompositeLengthException If {@code $futures} is empty.
 */
function awaitAny(iterable $futures, ?Cancellation $cancellation = null): mixed
{
    $result = awaitAnyN(1, $futures, $cancellation);
    return $result[\array_key_first($result)];
}

/**
 * Awaits the first N successfully completed futures, ignoring errors.
 *
 * @template Tk of array-key
 * @template Tv
 *
 * @param positive-int $count
 * @param iterable<Tk, Future<Tv>> $futures
 * @param Cancellation|null $cancellation Optional cancellation.
 *
 * @return non-empty-array<Tk, Tv>
 *
 * @throws CompositeException If too many futures errored.
 * @throws CompositeLengthException If {@code $futures} is empty.
 */
function awaitAnyN(int $count, iterable $futures, ?Cancellation $cancellation = null): array
{
    if ($count <= 0) {
        throw new \ValueError('Argument #1 ($count) must be greater than 0, got ' . $count);
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
        throw new CompositeLengthException('Argument #2 ($futures) contains too few futures to satisfy the required count of ' . $count);
    }

    /**
     * @var non-empty-array<Tk, \Throwable> $errors
     */
    throw new CompositeException($errors);
}

/**
 * Awaits all futures to complete or error.
 *
 * This awaits all futures without aborting on first error (unlike {@see await()}).
 *
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
 * Awaits all futures to complete or aborts if any errors.
 *
 * The returned array keys will be in the order the futures resolved, not in the order given by the iterable.
 * Sort the array after completion if necessary.
 *
 * This is equivalent to awaiting all futures in a loop, except that it aborts as soon as one of the futures errors
 * instead of relying on the order in the iterable and awaiting the futures sequentially.
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
