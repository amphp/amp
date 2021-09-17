<?php

namespace Amp;

use Amp\Internal\FutureIterator;
use Amp\Internal\FutureState;
use Revolt\EventLoop\Loop;
use function Revolt\EventLoop\defer;

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
                $iterator->enqueue($future->state, $key, $future);
            }
            $iterator->complete();
        } else {
            // Use separate fiber for iteration over non-array, because not all items might be immediately available
            // while other futures are already completed.
            defer(static function () use ($futures, $iterator): void {
                try {
                    foreach ($futures as $key => $future) {
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
    public static function complete(mixed $result): self
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
     * @internal Use {@see Deferred} to create and resolve a Future.
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
     * Awaits the operation to complete.
     *
     * Throws an exception if the operation fails.
     *
     * @return T
     */
    public function join(?CancellationToken $token = null): mixed
    {
        $suspension = Loop::createSuspension();

        $cancellationId = $token?->subscribe(function (\Throwable $reason) use (&$callbackId, $suspension): void {
            $this->state->unsubscribe($callbackId);
            if (!$this->state->isComplete()) { // Resume has already been scheduled if complete.
                $suspension->throw($reason);
            }
        });

        $callbackId = $this->state->subscribe(static function (?\Throwable $error, mixed $value) use (
            $cancellationId, $token, $suspension
        ): void {
            /** @psalm-suppress PossiblyNullArgument $cancellationId will not be null if $token is not null. */
            $token?->unsubscribe($cancellationId);

            if ($error) {
                $suspension->throw($error);
            } else {
                $suspension->resume($value);
            }
        });

        return $suspension->suspend();
    }
}
