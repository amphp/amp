<?php

namespace Amp\Internal;

use Amp\Stream;

/**
 * An stream that cannot externally emit values. Used by Emitter in development mode.
 *
 * @internal
 */
final class PrivateStream implements Stream {
    use CallableMaker, Producer;

    /**
     * @param callable(callable $emit, callable $complete, callable $fail): void $producer
     */
    public function __construct(callable $producer) {
        $producer(
            $this->callableFromInstanceMethod("emit"),
            $this->callableFromInstanceMethod("resolve"),
            $this->callableFromInstanceMethod("fail")
        );
    }
}
