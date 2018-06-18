<?php

namespace Amp\Internal;

use Amp\Promise;

/**
 * Wraps a Promise instance that has public methods to resolve and fail the promise into an object that only allows
 * access to the public API methods.
 */
class PrivatePromise implements Promise
{
    /** @var \Amp\Promise */
    private $promise;

    public function __construct(Promise $promise)
    {
        $this->promise = $promise;
    }

    public function onResolve(callable $onResolved)
    {
        $this->promise->onResolve($onResolved);
    }
}
