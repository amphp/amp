<?php

namespace Amp;

/**
 * Emitter is a container for an iterator that can emit values using the emit() method and completed using the
 * complete() and fail() methods of this object. The contained iterator may be accessed using the iterate()
 * method. This object should not be part of a public API, but used internally to create and emit values to an
 * iterator.
 */
final class Emitter {
    /** @var \Amp\Iterator */
    private $iterator;

    public function __construct() {
        $this->iterator = new class {
            use Internal\Producer {
                emit as public;
                complete as public;
                fail as public;
            }
        };
    }

    /**
     * @return \Amp\Promise
     */
    public function iterate(): Iterator {
        return $this->iterator->iterate();
    }

    /**
     * Emits a value to the iterator.
     *
     * @param mixed $value
     *
     * @return \Amp\Promise
     */
    public function emit($value): Promise {
        return $this->iterator->emit($value);
    }

    /**
     * Completes the iterator.
     */
    public function complete() {
        $this->iterator->complete();
    }

    /**
     * Fails the iterator with the given reason.
     *
     * @param \Throwable $reason
     */
    public function fail(\Throwable $reason) {
        $this->iterator->fail($reason);
    }
}
