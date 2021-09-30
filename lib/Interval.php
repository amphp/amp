<?php

namespace Amp;

use Revolt\EventLoop\Loop;
use function Revolt\EventLoop\queue;

/**
 * This object invokes the given callback within a new coroutine every $interval seconds until the {@see disable()}
 * method is called or the object is destroyed.
 */
final class Interval
{
    private string $watcher;

    private bool $enabled = true;

    /**
     * @param float $interval Invoke the function every $interval seconds.
     * @param callable(Interval):void $callback Callback is provided this instance to disable/unreference/etc.
     * @param bool $reference If false, unreference the underlying watcher.
     */
    public function __construct(
        float $interval,
        callable $callback,
        private bool $reference = true
    ) {
        $this->watcher = Loop::repeat($interval, weaken(fn () => queue(fn () => $callback($this))));
        if (!$reference) {
            Loop::unreference($this->watcher);
        }
    }

    public function __destruct()
    {
        Loop::cancel($this->watcher);
    }

    /**
     * References the internal watcher in the event loop, keeping the loop running while the repeat loop is active.
     *
     * @return $this
     */
    public function reference(): self
    {
        Loop::reference($this->watcher);
        $this->reference = true;
        return $this;
    }

    /**
     * Unreferences the internal watcher in the event loop, allowing the loop to stop while the repeat loop is active.
     *
     * @return $this
     */
    public function unreference(): self
    {
        Loop::unreference($this->watcher);
        $this->reference = false;
        return $this;
    }

    /**
     * @return bool True if the periodic function is still running.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return bool True if the periodic function is referenced.
     */
    public function isReferenced(): bool
    {
        return $this->reference;
    }

    /**
     * @return $this
     */
    public function enable(): self
    {
        Loop::enable($this->watcher);
        $this->enabled = true;
        return $this;
    }

    /**
     * Stop the repeat loop. Restart it with reset.
     *
     * @return $this
     */
    public function disable(): self
    {
        Loop::disable($this->watcher);
        $this->enabled = false;
        return $this;
    }
}
