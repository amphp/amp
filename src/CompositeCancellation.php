<?php declare(strict_types=1);

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
        $thatCancellations = &$this->cancellations;
        $onCancel = static function (CancelledException $exception) use (
            &$thatException,
            &$thatCallbacks,
            &$thatCancellations,
        ): void {
            if ($thatException) {
                return;
            }

            $thatException = $exception;

            foreach ($thatCancellations as [$cancellation, $id]) {
                /** @var Cancellation $cancellation */
                $cancellation->unsubscribe($id);
            }

            $thatCancellations = [];

            foreach ($thatCallbacks as $callback) {
                EventLoop::queue($callback, $exception);
            }

            $thatCallbacks = [];
        };

        foreach ($cancellations as $cancellation) {
            $id = $cancellation->subscribe($onCancel);
            $this->cancellations[] = [$cancellation, $id];
        }
    }

    public function __destruct()
    {
        foreach ($this->cancellations as [$cancellation, $id]) {
            /** @var Cancellation $cancellation */
            $cancellation->unsubscribe($id);
        }

        // The reference created in the constructor causes this property to persist beyond the life of this object,
        // so explicitly removing references will speed up garbage collection.
        $this->cancellations = [];
    }

    public function subscribe(\Closure $callback): string
    {
        $id = $this->nextId;
        \PHP_VERSION_ID >= 80300 ? $this->nextId = \str_increment($this->nextId) : ++$this->nextId;

        if ($this->exception) {
            EventLoop::queue($callback, $this->exception);
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
        return $this->exception !== null;
    }

    public function throwIfRequested(): void
    {
        if ($this->exception) {
            throw $this->exception;
        }
    }
}
