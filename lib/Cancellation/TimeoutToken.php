<?php

namespace Amp\Cancellation;

use Amp\Loop;
use Amp\TimeoutException;

/**
 * A TimeoutCancellationToken automatically requests cancellation after the timeout has elapsed.
 */
final class TimeoutToken implements Token
{
    /** @var string */
    private $watcher;

    /** @var Token */
    private $token;

    /**
     * @param int $timeout Milliseconds until cancellation is requested.
     */
    public function __construct(int $timeout)
    {
        $source = new TokenSource;
        $this->token = $source->getToken();

        $this->watcher = Loop::delay($timeout, static function () use ($source) {
            $source->cancel(new TimeoutException);
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
