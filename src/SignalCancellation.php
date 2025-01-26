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
     */
    public function __construct(int|array $signals, string $message = "Operation cancelled by signal")
    {
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
            $callbackIds[] = EventLoop::unreference(EventLoop::onSignal($signal, $callback));
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
}
