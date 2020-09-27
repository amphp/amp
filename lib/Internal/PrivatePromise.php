<?php

namespace Amp\Internal;

use Amp\Promise;

/**
 * Wraps a Placeholder instance that has public methods to resolve and fail the promise into an object that only allows
 * access to the public API methods.
 */
final class PrivatePromise implements Promise
{
    private Placeholder $placeholder;

    public function __construct(Placeholder $placeholder)
    {
        $this->placeholder = $placeholder;
    }

    public function onResolve(callable $onResolved): void
    {
        $this->placeholder->onResolve($onResolved);
    }
}
