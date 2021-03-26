<?php

namespace Amp;

use Revolt\EventLoop\Loop;

/**
 * A TimeoutCancellationToken automatically requests cancellation after the timeout has elapsed.
 */
final class TimeoutCancellationToken implements CancellationToken
{
    private string $watcher;

    private CancellationToken $token;

    /**
     * @param int    $timeout Milliseconds until cancellation is requested.
     * @param string $message Message for TimeoutException. Default is "Operation timed out".
     */
    public function __construct(int $timeout, string $message = "Operation timed out")
    {
        $source = new CancellationTokenSource;
        $this->token = $source->getToken();

        $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        $this->watcher = Loop::delay($timeout, static function () use ($source, $message, $trace): void {
            $trace = Internal\formatStacktrace($trace);
            $source->cancel(new TimeoutException("$message\r\nTimeoutCancellationToken was created here:\r\n$trace"));
        });

        Loop::unreference($this->watcher);
    }

    /**
     * Cancels the delay watcher.
     */
    public function __destruct()
    {
        Loop::cancel($this->watcher);
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(callable $callback): string
    {
        return $this->token->subscribe($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function unsubscribe(string $id): void
    {
        $this->token->unsubscribe($id);
    }

    /**
     * {@inheritdoc}
     */
    public function isRequested(): bool
    {
        return $this->token->isRequested();
    }

    /**
     * {@inheritdoc}
     */
    public function throwIfRequested(): void
    {
        $this->token->throwIfRequested();
    }
}
