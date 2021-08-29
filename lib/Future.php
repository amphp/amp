<?php

namespace Amp;

use Revolt\EventLoop\Loop;
use Amp\Internal\FutureIterator;
use Amp\Internal\FutureState;
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
     *
     * @return iterable<Tk, Future<Tv>>
     */
    public static function iterate(iterable $futures): iterable
    {
        $iterator = new Internal\FutureIterator;

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
        $state = new FutureState;
        $state->complete($result);

        return new self($state);
    }

    /**
     * @return Future<void>
     */
    public static function error(\Throwable $throwable): self
    {
        /** @var FutureState<void> $state */
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
    public function join(): mixed
    {
        $suspension = Loop::createSuspension();

        $this->state->subscribe(static function (?\Throwable $error, mixed $value) use ($suspension): void {
            if ($error) {
                $suspension->throw($error);
            } else {
                $suspension->resume($value);
            }
        });

        return $suspension->suspend();
    }
}
