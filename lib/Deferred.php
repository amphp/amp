<?php

namespace Amp;

/**
 * Deferred is a container for a promise that is resolved using the resolve() and fail() methods of this object.
 * The contained promise may be accessed using the promise() method. This object should not be part of a public
 * API, but used internally to create and resolve a promise.
 */
final class Deferred {
    /** @var object Has public resolve and fail methods. */
    private $resolver;

    /** @var \Amp\Promise Hides placeholder methods */
    private $promise;

    public function __construct() {
        $this->resolver = new class {
            use Internal\Placeholder {
                resolve as public;
                fail as public;
            }
        };

        $this->promise = new class($this->resolver) implements Promise {
            /** @var \Amp\Promise */
            private $promise;
            public function __construct($promise) {
                $this->promise = $promise;
            }
            public function onResolve(callable $onResolved) {
                $this->promise->onResolve($onResolved);
            }
        };
    }

    /**
     * @return \Amp\Promise
     */
    public function promise(): Promise {
        return $this->promise;
    }

    /**
     * Fulfill the promise with the given value.
     *
     * @param mixed $value
     */
    public function resolve($value = null) {
        $this->resolver->resolve($value);
    }

    /**
     * Fails the promise the the given reason.
     *
     * @param \Throwable $reason
     */
    public function fail(\Throwable $reason) {
        $this->resolver->fail($reason);
    }
}
