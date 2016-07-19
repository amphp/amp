<?php

namespace Amp;

use Interop\Async\Awaitable;
use Interop\Async\Loop;

/**
 * Creates an awaitable that resolves itself with a given value after a number of milliseconds.
 */
final class Pause implements Awaitable {
    use Internal\Placeholder;

    /**
     * @param int $time Milliseconds before succeeding the awaitable.
     * @param mixed $value Succeed the awaitable with this value.
     */
    public function __construct($time, $value = null)
    {
        Loop::delay($time, function () use ($value) {
            $this->resolve($value);
        });
    }
}
