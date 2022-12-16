<?php declare(strict_types=1);

namespace Amp\Internal;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\NullCancellation;
use Revolt\EventLoop;

/**
 * @template Tk
 * @template Tv
 *
 * @internal
 */
final class FutureIterator
{
    use ForbidCloning;
    use ForbidSerialization;

    /**
     * @var FutureIteratorQueue<Tk, Tv>
     */
    private readonly FutureIteratorQueue $queue;

    private readonly Cancellation $cancellation;

    private readonly string $cancellationId;

    /**
     * @var Future<null>|Future<never>|null
     */
    private ?Future $complete = null;

    public function __construct(?Cancellation $cancellation = null)
    {
        $this->queue = $queue = new FutureIteratorQueue();
        $this->cancellation = $cancellation ?? new NullCancellation();
        $this->cancellationId = $this->cancellation->subscribe(static function (\Throwable $reason) use ($queue): void {
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

        /**
         * @param Tv|null $result
         */
        $handler = static function (?\Throwable $error, mixed $result, string $id) use (
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
        };

        $id = $state->subscribe($handler);

        $queue->pending[$id] = $state;
    }

    public function complete(): void
    {
        if ($this->complete) {
            throw new \Error('Iterator has already been marked as complete');
        }

        $this->complete = Future::complete();

        if (!$this->queue->pending && $this->queue->suspension) {
            $this->queue->suspension->resume();
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
                return $this->complete->await();
            }

            $this->cancellation->throwIfRequested();

            $this->queue->suspension = EventLoop::getSuspension();

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
        $this->cancellation->unsubscribe($this->cancellationId);
        foreach ($this->queue->pending as $id => $state) {
            $state->unsubscribe($id);
        }
    }
}
