<?php

namespace Amp\Internal;

use Amp\CancellationToken;
use Amp\CancelledException;
use Revolt\EventLoop\Loop;

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

        $this->exception = new CancelledException($previous);

        $callbacks = $this->callbacks;
        $this->callbacks = [];

        foreach ($callbacks as $callback) {
            Loop::queue($callback, $this->exception);
        }
    }

    public function subscribe(callable $callback): string
    {
        $id = $this->nextId++;

        if ($this->exception) {
            Loop::queue($callback, $this->exception);
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
