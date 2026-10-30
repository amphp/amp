<?php declare(strict_types=1);

namespace Amp;

use Revolt\EventLoop;

/**
 * A SignalCancellation automatically requests cancellation when a given signal is received.
 *
 * @implements Cancellation[read-props|write-props|write-refs|io]
 *
 * @api
 */
final class SignalCancellation implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var list<string> */
    private readonly array $callbackIds;

    private readonly Internal\Cancellable $cancellation;

    /**
     * @param int|int[] $signals Signal number or array of signal numbers.
     * @param string $message Message for SignalException. Default is "Operation cancelled by signal".
     */
    public function __construct(int|array $signals, string $message = "Operation cancelled by signal")
    {
        if (\is_int($signals)) {
            $signals = [$signals];
        }

        $this->cancellation = $source = new Internal\Cancellable();

        $trace = \ini_get('zend.assertions') === '1' ? \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS) : null;

        $callbackIds = [];

        $callback = static function () use (&$callbackIds, $trace, $source, $message): void {
            foreach ($callbackIds as $callbackId) {
                EventLoop::cancel($callbackId);
            }

            if ($trace !== null) {
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

    /**
     * @psalm-capabilities read-props|write-props|write-refs|io
     */
    #[\Override]
    public function subscribe(\Closure $callback): string
    {
        return $this->cancellation->subscribe($callback);
    }

    /**
     * @psalm-capabilities read-props|write-props|write-refs
     */
    #[\Override]
    public function unsubscribe(string $id): void
    {
        $this->cancellation->unsubscribe($id);
    }

    /** @psalm-capabilities read-props */
    #[\Override]
    public function isRequested(): bool
    {
        return $this->cancellation->isRequested();
    }

    /**
     * @psalm-capabilities read-props|write-props|write-refs
     */
    #[\Override]
    public function throwIfRequested(): void
    {
        $this->cancellation->throwIfRequested();
    }
}
