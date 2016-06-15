<?php

namespace Amp;

use Interop\Async\Awaitable;
use Interop\Async\Loop;

/**
 * Creates an awaitable from a generator function yielding awaitables. When an awaitable is yielded, execution of the
 * generator is interrupted until the awaitable is resolved. The success value is sent to the generator, while the
 * failure reason is thrown into the generator. Using a coroutine, asynchronous code can be written without callbacks
 * and be structured like synchronous code.
 */
final class Coroutine implements Awaitable {
    use Internal\Placeholder;

    // Maximum number of immediate coroutine continuations before deferring next continuation to the loop.
    const MAX_CONTINUATION_DEPTH = 3;

    /**
     * @var \Generator
     */
    private $generator;

    /**
     * @var callable(\Throwable|\Exception|null $exception, mixed $value): void
     */
    private $when;

    /**
     * @var int
     */
    private $depth = 0;

    /**
     * @param \Generator $generator
     */
    public function __construct(\Generator $generator) {
        $this->generator = $generator;

        /**
         * @param \Throwable|\Exception|null $exception Exception to be thrown into the generator.
         * @param mixed $value The value to send to the generator.
         */
        $this->when = function ($exception, $value) {
            if (self::MAX_CONTINUATION_DEPTH < $this->depth) { // Defer continuation to avoid blowing up call stack.
                Loop::defer(function () use ($exception, $value) {
                    $when = $this->when;
                    $when($exception, $value);
                });
                return;
            }

            try {
                if ($exception) {
                    // Throw exception at current execution point.
                    $yielded = $this->generator->throw($exception);
                } else {
                    // Send the new value and execute to next yield statement.
                    $yielded = $this->generator->send($value);
                }

                if ($yielded instanceof Awaitable) {
                    ++$this->depth;
                    $yielded->when($this->when);
                    --$this->depth;
                    return;
                }

                // @todo Necessary for returning values in PHP 5.x. Remove once PHP 7 is required.
                if ($yielded instanceof Internal\CoroutineResult) {
                    $this->settle($yielded->getValue());
                    return;
                }

                if ($this->generator->valid()) {
                    throw new InvalidYieldException(
                        $this->generator,
                        $yielded,
                        \sprintf("Unexpected yield (%s or %s::result() expected)", Awaitable::class, self::class)
                    );
                }

                $this->resolve(PHP_MAJOR_VERSION >= 7 ? $this->generator->getReturn() : null);
            } catch (\Throwable $exception) {
                $this->fail($exception);
            } catch (\Exception $exception) {
                $this->fail($exception);
            }
        };

        try {
            $yielded = $this->generator->current();

            if ($yielded instanceof Awaitable) {
                ++$this->depth;
                $yielded->when($this->when);
                --$this->depth;
                return;
            }

            // @todo Necessary for returning values in PHP 5.x. Remove once PHP 7 is required.
            if ($yielded instanceof Internal\CoroutineResult) {
                $this->settle($yielded->getValue());
                return;
            }

            if ($this->generator->valid()) {
                throw new InvalidYieldException(
                    $this->generator,
                    $yielded,
                    \sprintf("Unexpected yield (%s or %s::result() expected)", Awaitable::class, self::class)
                );
            }

            $this->resolve(PHP_MAJOR_VERSION >= 7 ? $this->generator->getReturn() : null);
        } catch (\Throwable $exception) {
            $this->fail($exception);
        } catch (\Exception $exception) {
            $this->fail($exception);
        }
    }

    /**
     * Attempts to resolves the coroutine with the given value, failing if the generator is still valid after sending
     * the null back to the generator.
     * Required only for PHP 5.x. Remove once PHP 7 is required.
     *
     * @param mixed $result Coroutine return value.
     */
    private function settle($result) {
        $value = $this->generator->send(null);

        if ($this->generator->valid()) {
            $exception = new InvalidYieldException(
                $this->generator,
                $value,
                \sprintf("Unexpected yield after %s::result()", self::class)
            );

            do {
                $this->generator->throw($exception);
            } while ($this->generator->valid());

            throw $exception;
        }

        $this->resolve($result);
    }
    
    /**
     * Return a value from a coroutine. Required for PHP 5.x only. Use the return keyword in PHP 7.
     *
     * @param mixed $value
     *
     * @return \Amp\Internal\CoroutineResult
     */
    public static function result($value) {
        return new Internal\CoroutineResult($value);
    }
}
