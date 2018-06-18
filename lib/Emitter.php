<?php

namespace Amp;

/**
 * Emitter is a container for an iterator that can emit values using the emit() method and completed using the
 * complete() and fail() methods of this object. The contained iterator may be accessed using the iterate()
 * method. This object should not be part of a public API, but used internally to create and emit values to an
 * iterator.
 */
final class Emitter
{
    /** @var object Has public emit, complete, and fail methods. */
    private $emitter;

    /** @var \Amp\Iterator Hides producer methods. */
    private $iterator;

    public function __construct()
    {
        $this->emitter = new class implements Iterator {
            use Internal\Producer {
                emit as public;
                complete as public;
                fail as public;
            }
        };

        $this->iterator = new Internal\PrivateIterator($this->emitter);
    }

    /**
     * @return \Amp\Promise
     */
    public function iterate(): Iterator
    {
        return $this->iterator;
    }

    /**
     * Emits a value to the iterator.
     *
     * @param mixed $value
     *
     * @return \Amp\Promise
     */
    public function emit($value): Promise
    {
        return $this->emitter->emit($value);
    }

    /**
     * Completes the iterator.
     */
    public function complete()
    {
        $this->emitter->complete();
    }

    /**
     * Fails the iterator with the given reason.
     *
     * @param \Throwable $reason
     */
    public function fail(\Throwable $reason)
    {
        $this->emitter->fail($reason);
    }
}
