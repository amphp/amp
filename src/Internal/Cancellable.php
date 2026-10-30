<?php declare(strict_types=1);

namespace Amp\Internal;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Revolt\EventLoop;

/**
 * Cancellation with public cancellation method.
 *
 * @implements \Amp\Cancellation[read-props|write-this-props|write-refs|io]
 *
 * @internal
 */
final class Cancellable implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    private string $nextId = 'a';

    /** @var \Closure[] */
    private array $callbacks = [];

    private ?CancelledException $exception = null;

    private ?\Throwable $previous = null;

    private bool $requested = false;

    /** @psalm-capabilities read-props|write-this-props|write-refs|io */
    public function cancel(?\Throwable $previous = null): void
    {
        if ($this->requested) {
            return;
        }

        $this->requested = true;
        $this->previous = $previous;

        $callbacks = $this->callbacks;
        $this->callbacks = [];

        if (empty($callbacks)) {
            return;
        }

        $exception = $this->getException();

        foreach ($callbacks as $callback) {
            EventLoop::queue($callback, $exception);
        }
    }

    /** @psalm-capabilities read-props|write-this-props|write-refs */
    private function getException(): CancelledException
    {
        return $this->exception ??= new CancelledException($this->previous);
    }

    /**
     * @psalm-capabilities read-props|write-this-props|write-refs|io
     */
    #[\Override]
    public function subscribe(\Closure $callback): string
    {
        $id = $this->nextId;
        $this->nextId = \str_increment($this->nextId);

        if ($this->requested) {
            $exception = $this->getException();
            EventLoop::queue($callback, $exception);
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
        return $this->requested;
    }

    /**
     * @psalm-capabilities read-props|write-this-props|write-refs
     */
    #[\Override]
    public function throwIfRequested(): void
    {
        if ($this->requested) {
            throw $this->getException();
        }
    }
}
