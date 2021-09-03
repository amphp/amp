<?php

namespace Amp\Internal;

use Amp\CancellationToken;
use Amp\NullCancellationToken;
use Revolt\EventLoop\Loop;
use Amp\Future;

/**
 * @template Tk
 * @template Tv
 *
 * @internal
 */
final class FutureIterator
{
    /**
     * @var FutureIteratorQueue<Tk, Tv>
     */
    private FutureIteratorQueue $queue;

    private CancellationToken $token;

    private string $cancellationId;

    /**
     * @var Future<null>|Future<never-return>|null
     */
    private ?Future $complete = null;

    public function __construct(?CancellationToken $token = null)
    {
        $this->queue = $queue = new FutureIteratorQueue();
        $this->token = $token ?? new NullCancellationToken();

        $this->cancellationId = $this->token->subscribe(static function (\Throwable $reason) use ($queue): void {
            if ($queue->suspension) {
                $queue->suspension->throw($reason);
                $queue->suspension = null;
            }
        });
    }

    /**
     * @param FutureState<Tv> $state
     * @param Tk              $key
     * @param Future<Tv>      $future
     */
    public function enqueue(FutureState $state, mixed $key, Future $future): void
    {
        if ($this->complete) {
            throw new \Error('Iterator has already been marked as complete');
        }

        $queue = $this->queue; // Using separate object to avoid a circular reference.
        $id = $state->subscribe(
            /**
             * @param Tv|null $result
             */
            static function (?\Throwable $error, mixed $result, string $id) use (
                $key,
                $future,
                $queue
            ): void {
                unset($queue->pending[$id]);

                if ($queue->suspension) {
                    $queue->suspension->resume([$key, $future]);
                    $queue->suspension = null;
                    return;
                }

                $queue->items[] = [$key, $future];
            }
        );

        $queue->pending[$id] = $state;
    }

    public function complete(): void
    {
        if ($this->complete) {
            throw new \Error('Iterator has already been marked as complete');
        }

        $this->complete = Future::complete(null);

        if (!$this->queue->pending && $this->queue->suspension) {
            $this->queue->suspension->resume(null);
            $this->queue->suspension = null;
        }
    }

    public function error(\Throwable $exception): void
    {
        if ($this->complete) {
            throw new \Error('Iterator has already been marked as complete');
        }

        $this->complete = Future::error($exception);

        if (!$this->queue->pending && $this->queue->suspension) {
            $this->queue->suspension->throw($exception);
            $this->queue->suspension = null;
        }
    }

    /**
     * @return null|array{Tk, Future<Tv>}
     */
    public function consume(): ?array
    {
        if ($this->queue->suspension) {
            throw new \Error('Concurrent consume() operations are not supported');
        }

        if (!$this->queue->items) {
            if ($this->complete && !$this->queue->pending) {
                return $this->complete->join();
            }

            $this->queue->suspension = Loop::createSuspension();

            /** @var null|array{Tk, Future<Tv>} */
            return $this->queue->suspension->suspend();
        }

        $key = \array_key_first($this->queue->items);
        $item = $this->queue->items[$key];

        unset($this->queue->items[$key]);

        /** @var null|array{Tk, Future<Tv>} */
        return $item;
    }

    public function __destruct()
    {
        $this->token->unsubscribe($this->cancellationId);
        foreach ($this->queue->pending as $id => $state) {
            $state->unsubscribe($id);
        }
    }
}
