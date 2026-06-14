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
     * @param float $timeout Seconds until cancellation is requested.
     * @param string $message Message for TimeoutException. Default is "Operation timed out".
     */
    public function __construct(float $timeout, string $message = "Operation timed out")
    {
        $this->cancellation = $source = new Internal\Cancellable();

        \assert((bool) ($trace = \debug_backtrace(0)));

        $this->callbackId = EventLoop::delay($timeout, static function () use (&$trace, $source, $message): void {
            if ($trace ?? false) {
                $message .= \sprintf("\r\n%s was created here: %s", self::class, Internal\formatStacktrace($trace));
            } else {
                $message .= \sprintf(" (Enable assertions for a backtrace of the %s creation)", self::class);
            }

            $source->cancel(new TimeoutException($message));
        });

        EventLoop::unreference($this->callbackId);
    }

    /**
     * Cancels the delay watcher.
     */
    public function __destruct()
    {
        EventLoop::cancel($this->callbackId);
    }

    #[\Override]
    public function subscribe(\Closure $callback): string
    {
        return $this->cancellation->subscribe($callback);
    }

    #[\Override]
    public function unsubscribe(string $id): void
    {
        $this->cancellation->unsubscribe($id);
    }

    #[\Override]
    public function isRequested(): bool
    {
        return $this->cancellation->isRequested();
    }

    #[\Override]
    public function throwIfRequested(): void
    {
        $this->cancellation->throwIfRequested();
    }
}
