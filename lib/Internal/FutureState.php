<?php

namespace Amp\Internal;

use Revolt\EventLoop\Loop;
use Amp\Future;

/**
 * @internal
 *
 * @template T
 */
final class FutureState
{
    // Static so they can be used as array keys
    private static string $nextId = 'a';

    private bool $complete = false;

    /**
     * @var array<string, (callable(?\Throwable, ?T, string): void)>
     */
    private array $callbacks = [];

    /**
     * @var T|null
     */
    private mixed $result = null;

    private ?\Throwable $throwable = null;

    /**
     * Registers a callback to be notified once the operation is complete or errored.
     *
     * The callback is invoked directly from the event loop context, so suspension within the callback is not possible.
     *
     * @param (callable(?\Throwable, ?T, string): void) $callback Callback invoked on error / successful completion of the future.
     *
     * @return string Identifier that can be used to cancel interest for this future.
     */
    public function subscribe(callable $callback): string
    {
        $id = self::$nextId++;

        if ($this->complete) {
            Loop::queue($callback, $this->throwable, $this->result, $id);
        } else {
            $this->callbacks[$id] = $callback;
        }

        return $id;
    }

    /**
     * Cancels a subscription.
     *
     * Cancellations are advisory only. The callback might still be called if it is already queued for execution.
     *
     * @param string $id Identifier returned from subscribe()
     */
    public function unsubscribe(string $id): void
    {
        unset($this->callbacks[$id]);
    }

    /**
     * Completes the operation with a result value.
     *
     * @param T $result Result of the operation.
     */
    public function complete(mixed $result): void
    {
        if ($this->complete) {
            throw new \Error('Operation is no longer pending');
        }

        if ($result instanceof Future) {
            throw new \Error('Cannot complete with an instance of ' . Future::class);
        }

        $this->result = $result;
        $this->invokeCallbacks();
    }

    /**
     * Marks the operation as failed.
     *
     * @param \Throwable $throwable Throwable to indicate the error.
     */
    public function error(\Throwable $throwable): void
    {
        if ($this->complete) {
            throw new \Error('Operation is no longer pending');
        }

        $this->throwable = $throwable;
        $this->invokeCallbacks();
    }

    /**
     * @return bool True if the operation has completed.
     */
    public function isComplete(): bool
    {
        return $this->complete;
    }

    private function invokeCallbacks(): void
    {
        $this->complete = true;

        foreach ($this->callbacks as $id => $callback) {
            Loop::queue($callback, $this->throwable, $this->result, $id);
        }

        $this->callbacks = [];
    }
}
