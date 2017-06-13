<?php

namespace Amp;

/**
 * A TimeoutCancellationToken automatically requests cancellation after the timeout has elapsed.
 */
final class TimeoutCancellationToken implements CancellationToken {
    /** @var string */
    private $watcher;

    /** @var \Amp\CancellationToken */
    private $token;

    /**
     * @param int $timeout Milliseconds until cancellation is requested.
     */
    public function __construct(int $timeout) {
        $source = new CancellationTokenSource;
        $this->token = $source->getToken();

        $this->watcher = Loop::delay($timeout, static function () use ($source) {
            $source->cancel(new TimeoutException);
        });
        Loop::unreference($this->watcher);
    }

    /**
     * Cancels the delay watcher.
     */
    public function __destruct() {
        Loop::cancel($this->watcher);
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(callable $callback): string {
        return $this->token->subscribe($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function unsubscribe(string $id) {
        $this->token->unsubscribe($id);
    }

    /**
     * {@inheritdoc}
     */
    public function isRequested(): bool {
        return $this->token->isRequested();
    }

    /**
     * {@inheritdoc}
     */
    public function throwIfRequested() {
        $this->token->throwIfRequested();
    }
}
