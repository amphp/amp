<?php

namespace Amp\Internal;

use Amp\CancellationToken;

/**
 * @internal
 */
final class WrappedCancellationToken implements CancellationToken
{
    public function __construct(
        private CancellationToken $token
    ) {
    }

    public function subscribe(\Closure $callback): string
    {
        return $this->token->subscribe($callback);
    }

    public function unsubscribe(string $id): void
    {
        $this->token->unsubscribe($id);
    }

    public function isRequested(): bool
    {
        return $this->token->isRequested();
    }

    public function throwIfRequested(): void
    {
        $this->token->throwIfRequested();
    }
}
