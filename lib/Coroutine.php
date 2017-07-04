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

    private $immediate = true;
    private $exception;
    private $value;

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
            $this->exception = $exception;
            $this->value = $value;

            if (!$this->immediate) {
                $this->immediate = true;
                return;
            }

            try {
                do {
                    if ($this->exception) {
                        // Throw exception at current execution point.
                        $yielded = $this->generator->throw($this->exception);
                    } else {
                        // Send the new value and execute to next yield statement.
                        $yielded = $this->generator->send($this->value);
                    }

                    if (!$yielded instanceof Promise) {
                        if (!$this->generator->valid()) {
                            $this->resolve($this->generator->getReturn());
                            $this->onResolve = null;
                            return;
                        }

                        $yielded = $this->transform($yielded);
                    }

                    $this->immediate = false;
                    $yielded->onResolve($this->onResolve);
                } while ($this->immediate);

                $this->immediate = true;
            } catch (\Throwable $exception) {
                $this->fail($exception);
                $this->onResolve = null;
            } finally {
                $this->exception = null;
                $this->value = null;
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
            $this->fail($exception);
            $this->onResolve = null;
        }
    }

    /**
     * Attempts to transform the non-promise yielded from the generator into a promise, otherwise returns an instance
     * `Amp\Failure` failed with an instance of `Amp\InvalidYieldError`.
     *
     * @param mixed $yielded Non-promise yielded from generator.
     *
     * @return \Amp\Promise
     */
    private function transform($yielded): Promise {
        try {
            if (\is_array($yielded)) {
                return Promise\all($yielded);
            }

            if ($yielded instanceof ReactPromise) {
                return Promise\adapt($yielded);
            }

            if ($yielded instanceof CancellationToken) {
                $promise = $this->generator->key();

                if (!$promise instanceof Promise) {
                    if (\is_array($promise)) {
                        $promise = Promise\all($promise);
                    } elseif ($promise instanceof ReactPromise) {
                        $promise = Promise\adapt($promise);
                    } else {
                        return new Failure(new InvalidYieldError(
                            $this->generator,
                            \sprintf(
                                "Key must be an instance of %s or %s or array of such instances when yielding an instance of %s",
                                Promise::class,
                                ReactPromise::class,
                                CancellationToken::class
                            )
                        ));
                    }
                }

                return Promise\cancellable($promise, $yielded);
            }

            // No match, continue to returning Failure below.
        } catch (\Throwable $exception) {
            // Conversion to promise failed, fall-through to returning Failure below.
        }

        return new Failure(new InvalidYieldError(
            $this->generator,
            \sprintf(
                "Unexpected yield; Expected an instance of %s or %s or an array of such instances",
                Promise::class,
                ReactPromise::class
            ),
            $exception ?? null
        ));
    }
}
