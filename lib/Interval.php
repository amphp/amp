<?php

namespace Amp;

use Revolt\EventLoop;
use function Revolt\launch;

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
        $this->watcher = EventLoop::repeat($interval, weaken(fn () => launch(fn () => $callback($this))));
        if (!$reference) {
            EventLoop::unreference($this->watcher);
        }
    }

    public function __destruct()
    {
        EventLoop::cancel($this->watcher);
    }

    /**
     * References the internal watcher in the event loop, keeping the loop running while the repeat loop is active.
     *
     * @return $this
     */
    public function reference(): self
    {
        EventLoop::reference($this->watcher);
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
        EventLoop::unreference($this->watcher);
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
        EventLoop::enable($this->watcher);
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
        EventLoop::disable($this->watcher);
        $this->enabled = false;
        return $this;
    }
}
