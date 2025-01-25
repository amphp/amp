<?php declare(strict_types=1);

namespace Amp;

use Revolt\EventLoop;

/**
 * A TimeoutCancellation automatically requests cancellation after the timeout has elapsed.
 */
final class TimeoutCancellation implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    private readonly string $callbackId;

    private readonly Cancellation $cancellation;

    /**
     * @param float  $timeout Seconds until cancellation is requested.
     * @param string $message Message for TimeoutException. Default is "Operation timed out".
     * @param bool $reference If false, unreference the underlying event-loop callback.
     */
    public function __construct(
        float $timeout,
        string $message = "Operation timed out",
        bool $reference = false,
    ) {
        $this->cancellation = $source = new Internal\Cancellable;

        $trace = null; // Defined in case assertions are disabled.
        \assert((bool) ($trace = \debug_backtrace(0)));

        $this->callbackId = EventLoop::delay($timeout, static function () use ($source, $message, $trace): void {
            if ($trace) {
                $message .= \sprintf("\r\n%s was created here: %s", self::class, Internal\formatStacktrace($trace));
            } else {
                $message .= \sprintf(" (Enable assertions for a backtrace of the %s creation)", self::class);
            }

            $source->cancel(new TimeoutException($message));
        });

        if (!$reference) {
            EventLoop::unreference($this->callbackId);
        }
    }

    /**
     * Cancels the delay watcher.
     */
    public function __destruct()
    {
        EventLoop::cancel($this->callbackId);
    }

    public function subscribe(\Closure $callback): string
    {
        return $this->cancellation->subscribe($callback);
    }

    public function unsubscribe(string $id): void
    {
        $this->cancellation->unsubscribe($id);
    }

    public function isRequested(): bool
    {
        return $this->cancellation->isRequested();
    }

    public function throwIfRequested(): void
    {
        $this->cancellation->throwIfRequested();
    }

    /**
     * @return bool True if the internal event-loop callback is referenced, false if not or if the cancellation has
     *      occurred.
     */
    public function isReferenced(): bool
    {
        if ($this->cancellation->isRequested()) {
            return false;
        }

        return EventLoop::isReferenced($this->callbackId);
    }

    /**
     * References the internal event-loop callback, keeping the loop running while the timeout is applicable.
     * If the timeout has expired (cancellation has been requested), this method is a no-op.
     *
     * @return $this
     */
    public function reference(): self
    {
        if (!$this->cancellation->isRequested()) {
            EventLoop::reference($this->callbackId);
        }

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
}
