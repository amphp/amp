<?php

namespace Amp;

use Interop\Async\Awaitable;

/**
 * Objects returned from \Amp\Observable::subscribe() implement this interface.
 */
interface Disposable extends Awaitable {
    /**
     * Disposes of the subscriber, failing with an instance of \Amp\DisposedException
     */
    public function dispose();
}