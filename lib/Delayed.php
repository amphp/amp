<?php

namespace Amp;

/**
 * Creates a promise that resolves itself with a given value after a number of milliseconds.
 *
 * @template-covariant TReturn
 * @template-implements Promise<TReturn>
 */
final class Delayed implements Promise
{
    private Internal\Placeholder $placeholder;

    /** @var string|null Event loop watcher identifier. */
    private ?string $watcher;

    /**
     * @param int     $time Milliseconds before succeeding the promise.
     * @param TReturn $value Succeed the promise with this value.
     */
    public function __construct(int $time, mixed $value = null)
    {
        $this->placeholder = $placeholder = new Internal\Placeholder;

        $this->watcher = Loop::delay($time, function () use ($value, $placeholder): void {
            $this->watcher = null;
            $placeholder->resolve($value);
        });
    }

    /**
     * References the internal watcher in the event loop, keeping the loop running while this promise is pending.
     *
     * @return self
     */
    public function reference(): self
    {
        if ($this->watcher !== null) {
            Loop::reference($this->watcher);
        }

        return $this;
    }

    /**
     * Unreferences the internal watcher in the event loop, allowing the loop to stop while this promise is pending if
     * no other events are pending in the loop.
     *
     * @return self
     */
    public function unreference(): self
    {
        if ($this->watcher !== null) {
            Loop::unreference($this->watcher);
        }

        return $this;
    }

    /** @inheritDoc */
    public function onResolve(callable $onResolved): void
    {
        $this->placeholder->onResolve($onResolved);
    }
}
