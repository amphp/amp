<?php declare(strict_types=1);

namespace Amp;

use Revolt\EventLoop;

/**
 * A cancellation that is requested when any of the given cancellations is requested.
 *
 * @implements Cancellation[read-props|write-this-props|write-refs|io]
 *
 * @api
 */
final class CompositeCancellation implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var array<int, array{Cancellation, string}> */
    private array $cancellations = [];

    private string $nextId = 'a';

    /** @var array<string, \Closure(CancelledException): void> */
    private array $callbacks = [];

    private ?CancelledException $exception = null;

    public function __construct(Cancellation ...$cancellations)
    {
        // The callback shares these properties by reference and does not retain this object, so
        // destroying the cancellation unsubscribes while a child still holds the callback.
        $subscribed = [];
        $callbacks = [];
        $currentException = null;

        $this->cancellations = &$subscribed;
        $this->callbacks = &$callbacks;
        $this->exception = &$currentException;

        $onCancel = static function (CancelledException $exception) use (
            &$subscribed,
            &$callbacks,
            &$currentException,
        ): void {
            if ($currentException) {
                return;
            }

            $currentException = $exception;

            foreach ($subscribed as [$cancellation, $id]) {
                $cancellation->unsubscribe($id);
            }

            $subscribed = [];

            foreach ($callbacks as $callback) {
                EventLoop::queue($callback, $exception);
            }

            $callbacks = [];
        };

        foreach ($cancellations as $cancellation) {
            $id = $cancellation->subscribe($onCancel);
            $this->cancellations[] = [$cancellation, $id];
        }
    }

    public function __destruct()
    {
        foreach ($this->cancellations as [$cancellation, $id]) {
            $cancellation->unsubscribe($id);
        }

        // The reference created in the constructor causes this property to persist beyond the life of this object,
        // so explicitly removing references will speed up garbage collection.
        $this->cancellations = [];
    }

    /**
     * @psalm-capabilities read-props|write-this-props|write-refs|io
     */
    #[\Override]
    public function subscribe(\Closure $callback): string
    {
        $id = $this->nextId;
        $this->nextId = \str_increment($this->nextId);

        if ($this->exception) {
            EventLoop::queue($callback, $this->exception);
        } else {
            $this->callbacks[$id] = $callback;
        }

        return $id;
    }

    /**
     * @psalm-capabilities read-props|write-this-props|write-refs
     */
    #[\Override]
    public function unsubscribe(string $id): void
    {
        unset($this->callbacks[$id]);
    }

    /** @psalm-capabilities read-props */
    #[\Override]
    public function isRequested(): bool
    {
        if ($this->exception) {
            return true;
        }

        // Check each cancellation directly, as the callback setting the exception is invoked asynchronously.
        foreach ($this->cancellations as [$cancellation]) {
            if ($cancellation->isRequested()) {
                return true;
            }
        }

        return false;
    }

    /** @psalm-capabilities read-props|write-props|write-refs */
    #[\Override]
    public function throwIfRequested(): void
    {
        if ($this->exception) {
            throw $this->exception;
        }

        // Check each cancellation directly, as the callback setting the exception is invoked asynchronously.
        foreach ($this->cancellations as [$cancellation]) {
            $cancellation->throwIfRequested();
        }
    }
}
