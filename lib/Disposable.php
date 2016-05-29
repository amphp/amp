<?php

namespace Amp;

use Interop\Async\Awaitable;

interface Disposable extends Awaitable {
    /**
     * Disposes of the subscriber, failing with an instance of \Amp\DisposedException
     */
    public function dispose();
}