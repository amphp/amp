<?php

namespace Amp\Internal;

use Amp\CallableMaker;
use Amp\Promise;

/**
 * A promise that cannot be externally resolved. Used by Deferred in development mode.
 *
 * @internal
 */
final class PrivatePromise implements Promise {
    use CallableMaker, Placeholder;

    /**
     * @param callable (callable $resolve, callable $reject): void $resolver
     */
    public function __construct(callable $resolver) {
        $resolver(
            $this->callableFromInstanceMethod("resolve"),
            $this->callableFromInstanceMethod("fail")
        );
    }
}
