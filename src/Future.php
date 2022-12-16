<?php declare(strict_types=1);

namespace Amp;

use Amp\Internal\FutureIterator;
use Amp\Internal\FutureState;
use Revolt\EventLoop;

/**
 * @template-covariant T
 */
final class Future
{
    use ForbidCloning;
    use ForbidSerialization;

    /**
     * Iterate over the given futures in completion order.
     *
     * @template Tk
     * @template Tv
     *
     * @param iterable<Tk, Future<Tv>> $futures
     * @param Cancellation|null        $cancellation Optional cancellation.
     *
     * @return iterable<Tk, Future<Tv>>
     */
    public static function iterate(iterable $futures, ?Cancellation $cancellation = null): iterable
    {
        $iterator = new FutureIterator($cancellation);

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
     * @param Tv $value
     *
     * @return Future<Tv>
     */
    public static function complete(mixed $value = null): self
    {
        $state = new FutureState();
        $state->complete($value);

        return new self($state);
    }

    /**
     * @return Future<never>
     */
    public static function error(\Throwable $throwable): self
    {
        /** @var FutureState<never> $state */
        $state = new FutureState();
        $state->error($throwable);

        return new self($state);
    }

    /** @var FutureState<T> */
    private readonly FutureState $state;

    /**
     * @param FutureState<T> $state
     *
     * @internal Use {@see DeferredFuture} or {@see async()} to create and resolve a Future.
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
     *
     * @return Future<T>
     */
    public function ignore(): self
    {
        $this->state->ignore();

        return $this;
    }

    /**
     * Attaches a callback that is invoked if this future completes. The returned future is completed with the return
     * value of the callback, or errors with an exception thrown from the callback.
     *
     * @psalm-suppress InvalidTemplateParam
     *
     * @template Tr
     *
     * @param \Closure(T):Tr $map
     *
     * @return Future<Tr>
     */
    public function map(\Closure $map): self
    {
        $state = new FutureState();

        $this->state->subscribe(static function (?\Throwable $error, mixed $value) use ($state, $map): void {
            if ($error) {
                $state->error($error);
                return;
            }

            try {
                /** @var T $value */
                $state->complete($map($value));
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
     * @param \Closure(\Throwable):Tr $catch
     *
     * @return Future<Tr>
     */
    public function catch(\Closure $catch): self
    {
        $state = new FutureState();

        $this->state->subscribe(static function (?\Throwable $error, mixed $value) use ($state, $catch): void {
            if (!$error) {
                $state->complete($value);
                return;
            }

            try {
                $state->complete($catch($error));
            } catch (\Throwable $exception) {
                $state->error($exception);
            }
        });

        return new self($state);
    }

    /**
     * Attaches a callback that is always invoked when the future is completed. The returned future resolves with the
     * same value as this future once the callback has finished execution. If the callback throws, the returned future
     * will error with the thrown exception.
     *
     * @param \Closure():void $finally
     *
     * @return Future<T>
     */
    public function finally(\Closure $finally): self
    {
        $state = new FutureState();

        $this->state->subscribe(static function (?\Throwable $error, mixed $value) use ($state, $finally): void {
            try {
                $finally();

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
    public function await(?Cancellation $cancellation = null): mixed
    {
        $suspension = EventLoop::getSuspension();

        $callbackId = $this->state->subscribe(static function (?\Throwable $error, mixed $value) use (
            $suspension
        ): void {
            if ($error) {
                $suspension->throw($error);
            } else {
                $suspension->resume($value);
            }
        });

        $state = $this->state;
        $cancellationId = $cancellation?->subscribe(static function (\Throwable $reason) use (
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
            /** @psalm-suppress PossiblyNullArgument $cancellationId will not be null if $cancellation is not null. */
            $cancellation?->unsubscribe($cancellationId);
        }
    }
}
