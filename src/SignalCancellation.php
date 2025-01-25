<?php declare(strict_types=1);

namespace Amp;

use Revolt\EventLoop;

/**
 * A SignalCancellation automatically requests cancellation when a given signal is received.
 */
final class SignalCancellation implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var list<string> */
    private readonly array $callbackIds;

    private readonly Cancellation $cancellation;

    /**
     * @param int|int[] $signals Signal number or array of signal numbers.
     * @param string $message Message for SignalException. Default is "Operation cancelled by signal".
     * @param bool $reference If false, unreference the underlying event-loop callback.
     */
    public function __construct(
        int|array $signals,
        string $message = "Operation cancelled by signal",
        private bool $reference = false,
    ) {
        if (\is_int($signals)) {
            $signals = [$signals];
        }

        $this->cancellation = $source = new Internal\Cancellable;

        $trace = null; // Defined in case assertions are disabled.
        \assert((bool) ($trace = \debug_backtrace(0)));

        $callbackIds = [];

        $callback = static function () use (&$callbackIds, $source, $message, $trace): void {
            foreach ($callbackIds as $callbackId) {
                EventLoop::cancel($callbackId);
            }

            if ($trace) {
                $message .= \sprintf("\r\n%s was created here: %s", self::class, Internal\formatStacktrace($trace));
            } else {
                $message .= \sprintf(" (Enable assertions for a backtrace of the %s creation)", self::class);
            }

            $source->cancel(new SignalException($message));
        };

        foreach ($signals as $signal) {
            $callbackIds[] = $callbackId = EventLoop::onSignal($signal, $callback);

            if (!$reference) {
                EventLoop::unreference($callbackId);
            }
        }

        $this->callbackIds = $callbackIds;
    }

    /**
     * Cancels the delay watcher.
     */
    public function __destruct()
    {
        foreach ($this->callbackIds as $watcher) {
            EventLoop::cancel($watcher);
        }
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
        return $this->reference && !$this->cancellation->isRequested();
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
            foreach ($this->callbackIds as $callbackId) {
                EventLoop::reference($callbackId);
            }
        }

        $this->reference = true;

        return $this;
    }

    /**
     * Unreferences the internal event-loop callback, allowing the loop to stop while the repeat loop is enabled.
     *
     * @return $this
     */
    public function unreference(): self
    {
        foreach ($this->callbackIds as $callbackId) {
            EventLoop::unreference($callbackId);
        }

        $this->reference = false;

        return $this;
    }
}
