<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\{ Loop, Promise };

/**
 * Creates a promise that resolves itself with a given value after a number of milliseconds.
 */
final class Pause implements Promise {
    use Internal\Placeholder;

    /**
     * @param int $time Milliseconds before succeeding the promise.
     * @param mixed $value Succeed the promise with this value.
     */
    public function __construct(int $time, $value = null) {
        Loop::delay($time, function () use ($value) {
            $this->resolve($value);
        });
    }
}
