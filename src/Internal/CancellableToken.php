<?php

namespace Amp\Internal;

use Amp\CancellationToken;
use Amp\CancelledException;
use Revolt\EventLoop;

/**
 * Cancellation Token with public cancellation method.
 *
 * @internal
 */
final class CancellableToken implements CancellationToken
{
    private string $nextId = "a";

    /** @var callable[] */
    private array $callbacks = [];

    /** @var \Throwable|null */
    private ?\Throwable $exception = null;

    public function cancel(?\Throwable $previous = null): void
    {
        if (isset($this->exception)) {
            return;
        }

        $this->exception = $exception = new CancelledException($previous);

        $callbacks = $this->callbacks;
        $this->callbacks = [];

        foreach ($callbacks as $callback) {
            EventLoop::queue(static fn () => $callback($exception));
        }
    }

    public function subscribe(callable $callback): string
    {
        $id = $this->nextId++;

        if ($this->exception) {
            $exception = $this->exception;
            EventLoop::queue(static fn () => $callback($exception));
        } else {
            $this->callbacks[$id] = $callback;
        }

        return $id;
    }

    public function unsubscribe(string $id): void
    {
        unset($this->callbacks[$id]);
    }

    public function isRequested(): bool
    {
        return isset($this->exception);
    }

    public function throwIfRequested(): void
    {
        if (isset($this->exception)) {
            throw $this->exception;
        }
    }
}
