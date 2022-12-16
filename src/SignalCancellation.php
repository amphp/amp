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
    private readonly array $watchers;

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

        $watchers = [];

        $callback = static function () use (&$watchers, $source, $message, $trace): void {
            foreach ($watchers as $watcher) {
                EventLoop::cancel($watcher);
            }

            if ($trace) {
                $message .= \sprintf("\r\n%s was created here: %s", self::class, Internal\formatStacktrace($trace));
            } else {
                $message .= \sprintf(" (Enable assertions for a backtrace of the %s creation)", self::class);
            }

            $source->cancel(new SignalException($message));
        };

        foreach ($signals as $signal) {
            $watchers[] = EventLoop::unreference(EventLoop::onSignal($signal, $callback));
        }

        $this->watchers = $watchers;
    }

    /**
     * Cancels the delay watcher.
     */
    public function __destruct()
    {
        foreach ($this->watchers as $watcher) {
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
