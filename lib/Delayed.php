<?php

namespace Amp;

/**
 * Creates a promise that resolves itself with a given value after a number of milliseconds.
 */
final class Delayed implements Promise {
    use Internal\Placeholder;

    /** @var string Event loop watcher identifier. */
    private $watcher;

    /**
     * @param int   $time Milliseconds before succeeding the promise.
     * @param mixed $value Succeed the promise with this value.
     */
    public function __construct(int $time, $value = null) {
        $this->watcher = Loop::delay($time, function () use ($value) {
            $this->resolve($value);
        });
    }

    /**
     * References the internal watcher in the event loop, keeping the loop running while this promise is pending.
     */
    public function reference() {
        Loop::reference($this->watcher);
    }

    /**
     * Unreferences the internal watcher in the event loop, allowing the loop to stop while this promise is pending if
     * no other events are pending in the loop.
     */
    public function unreference() {
        Loop::unreference($this->watcher);
    }
}
