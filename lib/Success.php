<?php

namespace Amp;

use React\Promise\PromiseInterface as ReactPromise;

/**
 * Creates a successful promise using the given value (which can be any value except another object implementing
 * `Amp\Promise`).
 */
final class Success implements Promise {
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
            $result = $onResolved(null, $this->value);

            if ($result === null) {
                return;
            }

            if ($result instanceof \Generator) {
                $result = new Coroutine($result);
            }

            if ($result instanceof Promise || $result instanceof ReactPromise) {
                Promise\rethrow($result);
            }
        } catch (\Throwable $exception) {
            Loop::defer(function () use ($exception) {
                throw $exception;
            });
        }
    }
}
