<?php

namespace Amp
{

    use Concurrent\Awaitable;
    use Concurrent\Deferred;
    use Concurrent\Task;

    function delay(int $msDelay): void
    {
        $deferred = new Deferred;

        Loop::delay($msDelay, function () use ($deferred) {
            $deferred->resolve(null);
        });

        Task::await($deferred->awaitable());
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
        // Use Deferred::combine to save a new fiber instance
        Deferred::combine([$awaitable], function (Deferred $deferred, $last, $key, $error) {
            $deferred->resolve(); // dummy resolve

            if ($error) {
                Loop::defer(function () use ($error) {
                    throw $error;
                });
            }
        });
    }

    /**
     * Creates an artificial timeout for any `Promise`.
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
    function timeout($awaitable, int $timeout): Awaitable
    {
        $deferred = new Deferred;

        $watcher = Loop::delay($timeout, function () use (&$deferred) {
            $temp = $deferred; // prevent double resolve
            $deferred = null;
            $temp->fail(new TimeoutException);
        });

        Task::async(function () use (&$deferred, $awaitable, $watcher) {
            try {
                $value = Task::await($awaitable);

                if ($deferred !== null) {
                    Loop::cancel($watcher);
                    $deferred->resolve($value);
                }
            } catch (\Throwable $e) {
                if ($deferred !== null) {
                    Loop::cancel($watcher);
                    $deferred->fail($e);
                }
            }
        });

        return $deferred->awaitable();
    }
}
