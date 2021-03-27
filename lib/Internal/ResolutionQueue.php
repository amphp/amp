<?php

namespace Amp\Internal;

use Amp\Promise;
use Revolt\EventLoop\Loop;

/**
 * Stores a set of functions to be invoked when a promise is resolved.
 *
 * @internal
 * @psalm-internal Amp\Internal
 */
final class ResolutionQueue
{
    /** @var array<array-key, callable(\Throwable|null, mixed): (Promise|\React\Promise\PromiseInterface|\Generator<mixed,
     *     Promise|\React\Promise\PromiseInterface|array<array-key, Promise|\React\Promise\PromiseInterface>, mixed,
     *     mixed>|null) | callable(\Throwable|null, mixed): void> */
    private array $queue = [];

    /**
     * @param callable|null $callback Initial callback to add to queue.
     *
     * @psalm-param null|callable(\Throwable|null, mixed): (Promise|\React\Promise\PromiseInterface|\Generator<mixed,
     *     Promise|\React\Promise\PromiseInterface|array<array-key, Promise|\React\Promise\PromiseInterface>, mixed,
     *     mixed>|null) | callable(\Throwable|null, mixed): void $callback
     */
    public function __construct(callable $callback = null)
    {
        if ($callback !== null) {
            $this->push($callback);
        }
    }

    /**
     * Unrolls instances of self to avoid blowing up the call stack on resolution.
     *
     * @param callable $callback
     *
     * @psalm-param callable(\Throwable|null, mixed): (Promise|\React\Promise\PromiseInterface|\Generator<mixed,
     *     Promise|\React\Promise\PromiseInterface|array<array-key, Promise|\React\Promise\PromiseInterface>, mixed,
     *     mixed>|null) | callable(\Throwable|null, mixed): void $callback
     *
     * @return void
     */
    public function push(callable $callback): void
    {
        if ($callback instanceof self) {
            $this->queue = \array_merge($this->queue, $callback->queue);
            return;
        }

        $this->queue[] = $callback;
    }

    /**
     * Calls each callback in the queue, passing the provided values to the function.
     *
     * @param \Throwable|null $exception
     * @param mixed           $value
     *
     * @return void
     */
    public function __invoke(?\Throwable $exception, mixed $value): void
    {
        foreach ($this->queue as $callback) {
            try {
                $result = $callback($exception, $value);

                if ($result instanceof Promise) {
                    Promise\rethrow($result);
                }
            } catch (\Throwable $exception) {
                Loop::queue(static fn () => throw $exception);
            }
        }
    }
}
