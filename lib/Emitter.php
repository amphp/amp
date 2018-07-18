<?php

namespace Amp;

use Amp\Cancellation\CancelledException;
use Concurrent\Awaitable;
use Concurrent\Deferred;
use Concurrent\Task;

/**
 * Emitter is a container for an iterator that can emit values using the emit() method and completed using the
 * complete() and fail() methods of this object. The contained iterator may be accessed using the iterate()
 * method. This object should not be part of a public API, but used internally to create and emit values to an
 * iterator.
 */
final class Emitter
{
    /** @var Struct */
    private $state;

    /** @var \Iterator|null */
    private $iterator;

    public function __construct()
    {
        // Use a separate class for shared state, so __destruct works as expected.
        // The iterator below doesn't have a reference to the Emitter instance.
        $this->state = $state = new class
        {
            use Struct;

            /** @var boolean */
            public $complete = false;

            /** @var \Throwable|null */
            public $exception;

            /** @var mixed[] */
            public $values = [];

            /** @var Deferred[] */
            public $backpressure = [];

            /** @var int */
            public $position = -1;

            /** @var Deferred|null */
            public $waiting;
        };

        $this->iterator = (static function () use (&$state) {
            while (!$state->complete || $state->values) {
                if ($state->waiting !== null) {
                    throw new \Error("Can't move to the next element while another operation is pending");
                }

                if (isset($state->backpressure[$state->position])) {
                    /** @var Deferred $deferred */
                    $deferred = $state->backpressure[$state->position];
                    unset($state->values[$state->position], $state->backpressure[$state->position]);
                    $deferred->resolve();
                }

                ++$state->position;

                if (!$state->complete) {
                    $state->waiting = new Deferred;
                    Task::await($state->waiting->awaitable());
                }

                if ($state->exception) {
                    throw $state->exception;
                }

                if (\array_key_exists($state->position, $state->values)) {
                    yield null => $state->values[$state->position];
                }
            }
        })();
    }

    public function __destruct()
    {
        if (!$this->state->complete) {
            $this->fail(new CancelledException("The operation was cancelled, because the emitter was garbage collected without completing"));
        }
    }

    /**
     * Extract the iterator to return it to a caller.
     *
     * This will remove any reference to the iterator from this emitter.
     *
     * @return \Iterator
     */
    public function extractIterator(): \Iterator
    {
        if ($this->iterator === null) {
            throw new \Error("The emitter's iterator can only be extracted once!");
        }

        $iterator = $this->iterator;
        $this->iterator = null;

        return $iterator;
    }

    /**
     * Emits a value to the iterator.
     *
     * @param mixed $value
     */
    public function emit($value): void
    {
        if ($this->state->complete) {
            throw new \Error("Emitters can't emit values after calling complete");
        }

        if ($value instanceof Awaitable) {
            throw new \Error("Emitters can't emit instances of Awaitable, await before emitting");
        }

        $this->state->values[] = $value;
        $this->state->backpressure[] = $pressure = new Deferred;

        if ($this->state->waiting !== null) {
            /** @var Deferred $waiting */
            $waiting = $this->state->waiting;
            $this->state->waiting = null;
            $waiting->resolve(true);
        }

        Task::await($pressure->awaitable());
    }

    /**
     * Completes the iterator.
     */
    public function complete(): void
    {
        if ($this->state->complete) {
            throw new \Error("Emitters can only be completed once");
        }

        $this->state->complete = true;

        if ($this->state->waiting !== null) {
            /** @var Deferred $waiting */
            $waiting = $this->state->waiting;
            $this->state->waiting = null;
            $waiting->resolve(false);
        }
    }

    /**
     * Fails the emitter with the given reason.
     *
     * @param \Throwable $reason
     */
    public function fail(\Throwable $reason): void
    {
        $this->state->complete = true;
        $this->state->exception = $reason;

        if ($this->state->waiting !== null) {
            /** @var Deferred $waiting */
            $waiting = $this->state->waiting;
            $this->state->waiting = null;
            $waiting->fail($reason);
        }
    }
}
