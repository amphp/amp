<?php

namespace Amp;

use Revolt\EventLoop;

final class CompositeCancellation implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var array<int, array{Cancellation, string}> */
    private array $cancellations = [];

    private string $nextId = "a";

    /** @var array<string, \Closure(CancelledException): void> */
    private array $callbacks = [];

    private ?CancelledException $exception = null;

    public function __construct(Cancellation ...$cancellations)
    {
        $thatException = &$this->exception;
        $thatCallbacks = &$this->callbacks;

        foreach ($cancellations as $cancellation) {
            $id = $cancellation->subscribe(static function (CancelledException $exception) use (
                &$thatException,
                &$thatCallbacks
            ): void {
                $thatException = $exception;

                foreach ($thatCallbacks as $callback) {
                    EventLoop::queue($callback, $exception);
                }

                $thatCallbacks = [];
            });

            $this->cancellations[] = [$cancellation, $id];
        }
    }

    public function __destruct()
    {
        foreach ($this->cancellations as [$cancellation, $id]) {
            /** @var Cancellation $cancellation */
            $cancellation->unsubscribe($id);
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
        foreach ($this->cancellations as [$cancellation]) {
            if ($cancellation->isRequested()) {
                return true;
            }
        }

        return false;
    }

    /** @inheritdoc */
    public function throwIfRequested(): void
    {
        foreach ($this->cancellations as [$cancellation]) {
            $cancellation->throwIfRequested();
        }
    }
}
