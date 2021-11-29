<?php

namespace Amp\Internal;

use Amp\Future;
use Amp\Future\UnhandledFutureError;
use Revolt\EventLoop;

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

    private bool $handled = false;

    /**
     * @var array<string, callable(?\Throwable, ?T, string): void>|null
     */
    private ?array $callbacks = [];

    /**
     * @var T|null
     */
    private mixed $result = null;

    private ?\Throwable $throwable = null;

    public function __destruct()
    {
        if ($this->throwable && !$this->handled) {
            $throwable = new UnhandledFutureError($this->throwable);
            EventLoop::queue(static fn () => throw $throwable);
        }
    }

    /**
     * Registers a callback to be notified once the operation is complete or errored.
     *
     * The callback is invoked directly from the event loop context, so suspension within the callback is not possible.
     *
     * @param callable(?\Throwable, ?T, string): void $callback Callback invoked on error / successful completion of
     * the future.
     *
     * @return string Identifier that can be used to cancel interest for this future.
     */
    public function subscribe(callable $callback): string
    {
        $id = self::$nextId++;

        $this->handled = true; // Even if unsubscribed later, consider the future handled.

        if ($this->callbacks === null) {
            EventLoop::queue($callback, $this->throwable, $this->result, $id);
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

        $this->complete = true;

        if ($result instanceof Future) {
            EventLoop::queue(function () use ($result): void {
                try {
                    $this->result = $result->await();
                } catch (\Throwable $throwable) {
                    $this->throwable = $throwable;
                }

                $this->invokeCallbacks();
            });
            return;
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

        $this->complete = true;
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

    /**
     * @return bool
     */
    public function isSettled(): bool
    {
        return $this->callbacks === null;
    }

    /**
     * Suppress the exception thrown to the loop error handler if and operation error is not handled by a callback.
     */
    public function ignore(): void
    {
        $this->handled = true;
    }

    private function invokeCallbacks(): void
    {
        foreach ($this->callbacks as $id => $callback) {
            EventLoop::queue($callback, $this->throwable, $this->result, $id);
        }

        $this->callbacks = null;
    }
}
