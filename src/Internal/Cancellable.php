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
 * @internal
 */
final class Cancellable implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    private string $nextId = "a";

    /** @var \Closure[] */
    private array $callbacks = [];

    private ?CancelledException $exception = null;
    private ?\Throwable $previous = null;

    private bool $requested = false;

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
            EventLoop::queue(static fn () => $callback($exception));
        }
    }

    private function getException(): CancelledException
    {
        return $this->exception ??= new CancelledException($this->previous);
    }

    public function subscribe(\Closure $callback): string
    {
        $id = $this->nextId++;

        if ($this->requested) {
            $exception = $this->getException();
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
        return $this->requested;
    }

    public function throwIfRequested(): void
    {
        if ($this->requested) {
            throw $this->getException();
        }
    }
}
