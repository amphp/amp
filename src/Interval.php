<?php declare(strict_types=1);

namespace Amp;

use Revolt\EventLoop;

/**
 * This object invokes the given callback within a new coroutine every $interval seconds until either the
 * {@see self::disable()} method is called or the object is destroyed.
 */
final class Interval
{
    private readonly string $callbackId;

    /**
     * @param float $interval Invoke the function every $interval seconds.
     * @param \Closure():void $closure Use {@see weakClosure()} to avoid a circular reference if storing this object
     *      as a property of another object.
     * @param bool $reference If false, unreference the underlying event-loop callback.
     */
    public function __construct(float $interval, \Closure $closure, bool $reference = true)
    {
        $this->callbackId = EventLoop::repeat($interval, $closure);

        if (!$reference) {
            EventLoop::unreference($this->callbackId);
        }
    }

    public function __destruct()
    {
        EventLoop::cancel($this->callbackId);
    }

    /**
     * @return bool True if the internal event-loop callback is referenced.
     */
    public function isReferenced(): bool
    {
        return EventLoop::isReferenced($this->callbackId);
    }

    /**
     * References the internal event-loop callback, keeping the loop running while the repeat loop is enabled.
     *
     * @return $this
     */
    public function reference(): self
    {
        EventLoop::reference($this->callbackId);

        return $this;
    }

    /**
     * Unreferences the internal event-loop callback, allowing the loop to stop while the repeat loop is enabled.
     *
     * @return $this
     */
    public function unreference(): self
    {
        EventLoop::unreference($this->callbackId);

        return $this;
    }

    /**
     * @return bool True if the repeating timer is enabled.
     */
    public function isEnabled(): bool
    {
        return EventLoop::isEnabled($this->callbackId);
    }

    /**
     * Restart the repeating timer if previously stopped with {@see self::disable()}.
     *
     * @return $this
     */
    public function enable(): self
    {
        EventLoop::enable($this->callbackId);

        return $this;
    }

    /**
     * Stop the repeating timer. Restart it with {@see self::enable()}.
     *
     * @return $this
     */
    public function disable(): self
    {
        EventLoop::disable($this->callbackId);

        return $this;
    }
}
