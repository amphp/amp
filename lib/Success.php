<?php

namespace Amp;

use React\Promise\PromiseInterface as ReactPromise;

/**
 * Creates a successful stream (which is also a promise) using the given value (which can be any value except another
 *  object implementing \Amp\Promise).
 */
final class Success implements Stream {
    /** @var mixed */
    private $value;

    /**
     * @param mixed $value Anything other than a Promise object.
     *
     * @throws \Error If a promise is given as the value.
     */
    public function __construct($value = null) {
        if ($value instanceof Promise || $value instanceof ReactPromise) {
            throw new \Error("Cannot use a promise as success value");
        }

        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function onResolve(callable $onResolved) {
        try {
            $onResolved(null, $this->value);
        } catch (\Throwable $exception) {
            Loop::defer(function () use ($exception) {
                throw $exception;
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onEmit(callable $onEmit) {
    }
}
