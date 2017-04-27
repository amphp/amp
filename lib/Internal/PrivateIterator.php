<?php

namespace Amp\Internal;

use Amp\CallableMaker;
use Amp\Iterator;

/**
 * An iterator that cannot externally emit values. Used by Emitter in development mode.
 *
 * @internal
 */
final class PrivateIterator implements Iterator {
    use CallableMaker, Producer;

    /**
     * @param callable (callable $emit, callable $complete, callable $fail): void $producer
     */
    public function __construct(callable $producer) {
        $producer(
            $this->callableFromInstanceMethod("emit"),
            $this->callableFromInstanceMethod("complete"),
            $this->callableFromInstanceMethod("fail")
        );
    }
}
