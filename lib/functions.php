<?php

namespace Amp
{

    use Concurrent\Awaitable;
    use Concurrent\Deferred;
    use Concurrent\Task;
    use Concurrent\Timer;
    use function Concurrent\race;

    function delay(int $msDelay): void
    {
        $timer = new Timer($msDelay);
        $timer->awaitTimeout();
    }

    /**
     * Registers a callback that will forward the failure reason to the event loop's error handler if the awaitable
     * fails.
     *
     * Use this function if you neither return the promise nor handle a possible error yourself to prevent errors from
     * going entirely unnoticed.
     *
     * @param Awaitable $awaitable Awaitable to register the handler on.
     */
    function rethrow(Awaitable $awaitable)
    {
        // Use Deferred::transform to save a new fiber instance
        Deferred::transform($awaitable, function ($error) {
            if ($error) {
                \trigger_error("Uncaught exception: " . (string) $error, \E_USER_ERROR);
            }
        });
    }

    /**
     * Creates an artificial timeout for any `Awaitable`.
     *
     * If the timeout expires before the awaitable is resolved, the returned awaitable fails with an instance of
     * `Amp\TimeoutException`.
     *
     * @param Awaitable $awaitable Awaitable to which the timeout is applied.
     * @param int       $timeout Timeout in milliseconds.
     *
     * @return Awaitable
     *
     * @throws TimeoutException
     */
    function timeout(Awaitable $awaitable, int $timeout): Awaitable
    {
        $timeoutAwaitable = Task::async(function () use ($timeout) {
            $timer = new Timer($timeout);
            $timer->awaitTimeout();

            throw new TimeoutException("Operation timed out");
        });

        return Task::await(race([$awaitable, $timeoutAwaitable]));
    }

    function some(array $awaitables, int $required = 1)
    {
        if ($required < 0) {
            throw new \Error("Number of promises required must be non-negative");
        }

        $pending = \count($awaitables);

        if ($required > $pending) {
            throw new \Error("Too few promises provided");
        }
        if (empty($awaitables)) {
            return [[], []];
        }

        $values = [];
        $errors = [];

        foreach ($awaitables as $key => $awaitable) {
            $values[$key] = null;
            $errors[$key] = null;
        }

        return Deferred::combine($awaitables, function (
            Deferred $deferred,
            bool $last,
            $key,
            ?\Throwable $error,
            $value
        ) use (
            &$values,
            &$errors,
            $required
        ) {
            if ($error) {
                $errors[$key] = $error;
                unset($values[$key]);
            } else {
                $values[$key] = $value;
                unset($errors[$key]);
            }

            if ($last) {
                if (\count($values) < $required) {
                    $deferred->fail(new MultiReasonException($errors));
                } else {
                    $deferred->resolve([$errors, $values]);
                }
            }
        });
    }
}
