<?php

namespace Amp;

use Revolt\EventLoop;

final class CompositeCancellation implements Cancellation
{
    /** @var array<int, array{Cancellation, string}> */
    private array $cancellations = [];

    private string $nextId = "a";

    /** @var \Closure(CancelledException)[] */
    private array $callbacks = [];

    private ?CancelledException $exception = null;

    public function __construct(Cancellation ...$cancellations)
    {
        foreach ($cancellations as $cancellation) {
            $id = $cancellation->subscribe(function (CancelledException $exception): void {
                $this->exception = $exception;

                foreach ($this->callbacks as $callback) {
                    EventLoop::queue($callback, $exception);
                }

                $this->callbacks = [];
            });

            $this->cancellations[] = [$cancellation, $id];
        }
    }

    public function __destruct()
    {
        foreach ($this->cancellations as [$token, $id]) {
            /** @var Cancellation $token */
            $token->unsubscribe($id);
        }
    }

    public function subscribe(\Closure $callback): string
    {
        $id = $this->nextId++;

        if ($this->exception) {
            EventLoop::queue($callback, $this->exception);
        } else {
            $this->callbacks[$id] = $callback;
        }

        return $id;
    }

    /** @inheritdoc */
    public function unsubscribe(string $id): void
    {
        unset($this->callbacks[$id]);
    }

    /** @inheritdoc */
    public function isRequested(): bool
    {
        foreach ($this->cancellations as [$token]) {
            if ($token->isRequested()) {
                return true;
            }
        }

        return false;
    }

    /** @inheritdoc */
    public function throwIfRequested(): void
    {
        foreach ($this->cancellations as [$token]) {
            $token->throwIfRequested();
        }
    }
}
