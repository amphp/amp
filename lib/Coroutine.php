<?php

namespace Amp;

use function Amp\Internal\formatStacktrace;
use React\Promise\PromiseInterface as ReactPromise;

/**
 * Creates a promise from a generator function yielding promises.
 *
 * When a promise is yielded, execution of the generator is interrupted until the promise is resolved. A success
 * value is sent into the generator, while a failure reason is thrown into the generator. Using a coroutine,
 * asynchronous code can be written without callbacks and be structured like synchronous code.
 */
final class Coroutine implements Promise
{
    use Internal\Placeholder;

    /**
     * Attempts to transform the non-promise yielded from the generator into a promise, otherwise returns an instance
     * `Amp\Failure` failed with an instance of `Amp\InvalidYieldError`.
     *
     * @param mixed      $yielded Non-promise yielded from generator.
     * @param \Generator $generator No type for performance, we already know the type.
     *
     * @return Promise
     */
    private static function transform($yielded, $generator): Promise
    {
        try {
            if (\is_array($yielded)) {
                return Promise\all($yielded);
            }

            if ($yielded instanceof ReactPromise) {
                return Promise\adapt($yielded);
            }

            // No match, continue to returning Failure below.
        } catch (\Throwable $exception) {
            // Conversion to promise failed, fall-through to returning Failure below.
        }

        return new Failure(new InvalidYieldError(
            $generator,
            \sprintf(
                "Unexpected yield; Expected an instance of %s or %s or an array of such instances",
                Promise::class,
                ReactPromise::class
            ),
            $exception ?? null
        ));
    }

    /** @var string Timeout watcher for each step. */
    private $timeoutWatcher;

    /** @var string Debug trace. */
    private $trace;

    /**
     * @param \Generator $generator
     */
    public function __construct(\Generator $generator)
    {
        $this->timeoutWatcher = Loop::delay(1000, function () {
            fwrite(STDERR, $this->trace . "\r\n");
        });
        $this->trace = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));

        try {
            $yielded = $generator->current();

            if (!$yielded instanceof Promise) {
                if (!$generator->valid()) {
                    Loop::cancel($this->timeoutWatcher);
                    $this->resolve($generator->getReturn());
                    return;
                }

                $yielded = self::transform($yielded, $generator);
            }
        } catch (\Throwable $exception) {
            Loop::cancel($this->timeoutWatcher);
            $this->fail($exception);
            return;
        }

        /**
         * @param \Throwable|null $e Exception to be thrown into the generator.
         * @param mixed           $v Value to be sent into the generator.
         */
        $onResolve = function ($e, $v) use ($generator, &$onResolve) {
            // Reset the timeout
            Loop::disable($this->timeoutWatcher);
            Loop::enable($this->timeoutWatcher);
            $this->trace = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));

            /** @var bool Used to control iterative coroutine continuation. */
            static $immediate = true;

            /** @var \Throwable|null Promise failure reason when executing next coroutine step, null at all other times. */
            static $exception;

            /** @var mixed Promise success value when executing next coroutine step, null at all other times. */
            static $value;

            $exception = $e;
            $value = $v;

            if (!$immediate) {
                $immediate = true;
                return;
            }

            try {
                try {
                    do {
                        if ($exception) {
                            // Throw exception at current execution point.
                            $yielded = $generator->throw($exception);
                        } else {
                            // Send the new value and execute to next yield statement.
                            $yielded = $generator->send($value);
                        }

                        if (!$yielded instanceof Promise) {
                            if (!$generator->valid()) {
                                Loop::cancel($this->timeoutWatcher);
                                $this->resolve($generator->getReturn());
                                $onResolve = null;
                                return;
                            }

                            $yielded = self::transform($yielded, $generator);
                        }

                        $immediate = false;
                        $yielded->onResolve($onResolve);
                    } while ($immediate);

                    $immediate = true;
                } catch (\Throwable $exception) {
                    Loop::cancel($this->timeoutWatcher);
                    $this->fail($exception);
                    $onResolve = null;
                } finally {
                    $exception = null;
                    $value = null;
                }
            } catch (\Throwable $e) {
                Loop::defer(static function () use ($e) {
                    throw $e;
                });
            }
        };

        try {
            $yielded->onResolve($onResolve);

            unset($generator, $yielded, $onResolve);
        } catch (\Throwable $e) {
            Loop::defer(static function () use ($e) {
                throw $e;
            });
        }
    }
}
