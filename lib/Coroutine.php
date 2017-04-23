<?php

namespace Amp;

use React\Promise\PromiseInterface as ReactPromise;

/**
 * Creates a promise from a generator function yielding promises.
 *
 * When a promise is yielded, execution of the generator is interrupted until the promise is resolved. A success
 * value is sent into the generator, while a failure reason is thrown into the generator. Using a coroutine,
 * asynchronous code can be written without callbacks and be structured like synchronous code.
 */
final class Coroutine implements Promise {
    use Internal\Placeholder;

    /** @var \Generator */
    private $generator;

    /** @var callable(\Throwable|null $exception, mixed $value): void */
    private $onResolve;

    /**
     * @param \Generator $generator
     */
    public function __construct(\Generator $generator) {
        $this->generator = $generator;

        /**
         * @param \Throwable|null $exception Exception to be thrown into the generator.
         * @param mixed           $value Value to be sent into the generator.
         */
        $this->onResolve = function ($exception, $value) {
            try {
                if ($exception) {
                    // Throw exception at current execution point.
                    $yielded = $this->generator->throw($exception);
                } else {
                    // Send the new value and execute to next yield statement.
                    $yielded = $this->generator->send($value);
                }

                if (!$yielded instanceof Promise) {
                    if (!$this->generator->valid()) {
                        $this->resolve($this->generator->getReturn());
                        $this->onResolve = null;
                        return;
                    }

                    $yielded = $this->transform($yielded);
                }

                $yielded->onResolve($this->onResolve);
            } catch (\Throwable $exception) {
                $this->dispose($exception);
            }
        };

        try {
            $yielded = $this->generator->current();

            if (!$yielded instanceof Promise) {
                if (!$this->generator->valid()) {
                    $this->resolve($this->generator->getReturn());
                    $this->onResolve = null;
                    return;
                }

                $yielded = $this->transform($yielded);
            }

            $yielded->onResolve($this->onResolve);
        } catch (\Throwable $exception) {
            $this->dispose($exception);
        }
    }

    /**
     * Attempts to transform the non-promise yielded from the generator into a promise, otherwise throws an instance
     * of `Amp\InvalidYieldError`.
     *
     * @param mixed $yielded Non-promise yielded from generator.
     *
     * @return \Amp\Promise
     *
     * @throws \Amp\InvalidYieldError If the value could not be converted to a promise.
     */
    private function transform($yielded): Promise {
        try {
            if (\is_array($yielded)) {
                return Promise\all($yielded);
            }

            if ($yielded instanceof ReactPromise) {
                return Promise\adapt($yielded);
            }

            // No match, continue to throwing error below.
        } catch (\Throwable $exception) {
            // Conversion to promise failed, fall-through to throwing error below.
        }

        throw new InvalidYieldError(
            $this->generator,
            \sprintf(
                "Unexpected yield; Expected an instance of %s or %s or an array of such instances",
                Promise::class,
                ReactPromise::class
            ),
            $exception ?? null
        );
    }

    /**
     * Runs the generator to completion then fails the coroutine with the given exception.
     *
     * @param \Throwable $exception
     */
    private function dispose(\Throwable $exception) {
        if ($this->generator->valid()) {
            try {
                try {
                    // Ensure generator has run to completion to avoid throws from finally blocks on destruction.
                    do {
                        $this->generator->throw($exception);
                    } while ($this->generator->valid());
                } finally {
                    // Throw from finally to attach any exception thrown from generator as previous exception.
                    throw $exception;
                }
            } catch (\Throwable $exception) {
                // $exception will be used to fail the coroutine.
            }
        }

        $this->fail($exception);
        $this->onResolve = null;
    }
}
