<?php

namespace Amp;

use Amp\Internal\FutureIterator;
use Amp\Internal\FutureState;
use Revolt\EventLoop;

/**
 * @template T
 */
final class Future
{
    /**
     * Iterate over the given futures in completion order.
     *
     * @template Tk
     * @template Tv
     *
     * @param iterable<Tk, Future<Tv>> $futures
     * @param CancellationToken|null $token Optional cancellation token.
     *
     * @return iterable<Tk, Future<Tv>>
     */
    public static function iterate(iterable $futures, ?CancellationToken $token = null): iterable
    {
        $iterator = new FutureIterator($token);

        // Directly iterate in case of an array, because there can't be suspensions during iteration
        if (\is_array($futures)) {
            foreach ($futures as $key => $future) {
                if (!$future instanceof self) {
                    throw new \TypeError('Array must only contain instances of ' . self::class);
                }
                $iterator->enqueue($future->state, $key, $future);
            }
            $iterator->complete();
        } else {
            // Use separate fiber for iteration over non-array, because not all items might be immediately available
            // while other futures are already completed.
            EventLoop::queue(static function () use ($futures, $iterator): void {
                try {
                    foreach ($futures as $key => $future) {
                        if (!$future instanceof self) {
                            throw new \TypeError('Iterable must only provide instances of ' . self::class);
                        }
                        $iterator->enqueue($future->state, $key, $future);
                    }
                    $iterator->complete();
                } catch (\Throwable $exception) {
                    $iterator->error($exception);
                }
            });
        }

        while ($item = $iterator->consume()) {
            yield $item[0] => $item[1];
        }
    }

    /**
     * @template Tv
     *
     * @param Tv $result
     *
     * @return Future<Tv>
     */
    public static function complete(mixed $result = null): self
    {
        $state = new FutureState();
        $state->complete($result);

        return new self($state);
    }

    /**
     * @return Future<never-return>
     */
    public static function error(\Throwable $throwable): self
    {
        /** @var FutureState<never-return> $state */
        $state = new FutureState();
        $state->error($throwable);

        return new self($state);
    }

    /** @var FutureState<T> */
    private FutureState $state;

    /**
     * @param FutureState<T> $state
     *
     * @internal Use {@see Deferred} or {@see async()} to create and resolve a Future.
     */
    public function __construct(FutureState $state)
    {
        $this->state = $state;
    }

    /**
     * @return bool True if the operation has completed.
     */
    public function isComplete(): bool
    {
        return $this->state->isComplete();
    }

    /**
     * Do not forward unhandled errors to the event loop handler.
     */
    public function ignore(): void
    {
        $this->state->ignore();
    }

    /**
     * Attaches a callback that is invoked if this future completes. The returned future is completed with the return
     * value of the callback, or errors with an exception thrown from the callback.
     *
     * @template Tr
     *
     * @param callable(T):Tr $onComplete
     * @return Future
     */
    public function map(callable $onComplete): self
    {
        $state = new FutureState();

        $this->state->subscribe(static function (?\Throwable $error, mixed $value) use ($state, $onComplete): void {
            if ($error) {
                $state->error($error);
                return;
            }

            try {
                $state->complete($onComplete($value));
            } catch (\Throwable $exception) {
                $state->error($exception);
            }
        });

        return new self($state);
    }

    /**
     * Attaches a callback that is invoked if this future errors. The returned future is completed with the return
     * value of the callback, or errors with an exception thrown from the callback.
     *
     * @template Tr
     *
     * @param callable(\Throwable):Tr $onError
     * @return Future
     */
    public function catch(callable $onError): self
    {
        $state = new FutureState();

        $this->state->subscribe(static function (?\Throwable $error, mixed $value) use ($state, $onError): void {
            if (!$error) {
                $state->complete($value);
                return;
            }

            try {
                $state->complete($onError($error));
            } catch (\Throwable $exception) {
                $state->error($exception);
            }
        });

        return new self($state);
    }

    /**
     * Attaches a callback that is always invoked when the future is settled. The returned future resolves with the
     * same value as this future once the callback has finished execution. If the callback throws, the returned future
     * will error with the thrown exception.
     *
     * @param callable():void $onSettle
     * @return Future<T>
     */
    public function finally(callable $onSettle): self
    {
        $state = new FutureState();

        $this->state->subscribe(static function (?\Throwable $error, mixed $value) use ($state, $onSettle): void {
            try {
                $onSettle();

                if ($error) {
                    $state->error($error);
                } else {
                    $state->complete($value);
                }
            } catch (\Throwable $exception) {
                $state->error($exception);
            }
        });

        return new self($state);
    }

    /**
     * Awaits the operation to complete.
     *
     * Throws an exception if the operation fails.
     *
     * @return T
     */
    public function await(?CancellationToken $token = null): mixed
    {
        $suspension = EventLoop::createSuspension();

        $callbackId = $this->state->subscribe(static function (?\Throwable $error, mixed $value) use (
            $token,
            $suspension
        ): void {
            if ($error) {
                $suspension->throw($error);
            } else {
                $suspension->resume($value);
            }
        });

        $state = $this->state;
        $cancellationId = $token?->subscribe(static function (\Throwable $reason) use (
            $callbackId,
            $suspension,
            $state
        ): void {
            $state->unsubscribe($callbackId);
            if (!$state->isComplete()) { // Resume has already been scheduled if complete.
                $suspension->throw($reason);
            }
        });

        try {
            return $suspension->suspend();
        } finally {
            /** @psalm-suppress PossiblyNullArgument $cancellationId will not be null if $token is not null. */
            $token?->unsubscribe($cancellationId);
        }
    }
}
