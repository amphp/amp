<?php

namespace Amp\Awaitable;

use Interop\Async\Awaitable;
use Interop\Async\Loop;

final class Coroutine implements Awaitable {
    use Internal\Placeholder;

    // Maximum number of immediate coroutine continuations before deferring next continuation to the loop.
    const MAX_RECURSION_DEPTH = 3;

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
        $this->when = function ($exception = null, $value = null) {
            if (self::MAX_RECURSION_DEPTH < $this->depth) { // Defer continuation to avoid blowing up call stack.
                Loop::defer(function () use ($exception, $value) {
                    $when = $this->when;
                    $when($exception, $value);
                });
                return;
            }

            try {
                if ($exception) {
                    // Throw exception at current execution point.
                    $this->next($this->generator->throw($exception));
                    return;
                }

                // Send the new value and execute to next yield statement.
                $this->next($this->generator->send($value));
            } catch (\Throwable $exception) {
                $this->fail($exception);
            } catch (\Exception $exception) {
                $this->fail($exception);
            }
        };

        try {
            $this->next($this->generator->current());
        } catch (\Throwable $exception) {
            $this->fail($exception);
        } catch (\Exception $exception) {
            $this->fail($exception);
        }
    }

    /**
     * Examines the value yielded from the generator and prepares the next step in iteration.
     *
     * @param mixed $yielded Value yielded from generator.
     */
    private function next($yielded) {
        if (!$this->generator->valid()) {
            $this->resolve(PHP_MAJOR_VERSION >= 7 ? $this->generator->getReturn() : null);
            return;
        }

        ++$this->depth;

        if ($yielded instanceof Awaitable) {
            $yielded->when($this->when);
        } elseif ($yielded instanceof Internal\CoroutineResult) {
            // @todo Necessary for returning values in PHP 5.x. Remove once PHP 7 is required.
            $yielded = $yielded->getValue();
            try {
                $value = $this->generator->send($yielded);
                
                if ($this->generator->valid()) {
                    $exception = new Exception\InvalidYieldException(
                        $this->generator,
                        $value,
                        "Unexpected yield after coroutine result"
                    );

                    do {
                        $this->generator->throw($exception);
                    } while ($this->generator->valid());

                    $this->fail($exception);
                } else {
                    $this->resolve($yielded);
                }
            } catch (\Throwable $exception) {
                $this->fail($exception);
            } catch (\Exception $exception) {
                $this->fail($exception);
            }
        } else {
            throw new Exception\InvalidYieldException(
                $this->generator,
                $yielded,
                "Unexpected yield (Awaitable expected)"
            );
        }

        --$this->depth;
    }
    
    /**
     * Return a value from a coroutine. Required for PHP 5.x only. Use the return keyword in PHP 7.
     *
     * @param mixed $value
     *
     * @return \Amp\Awaitable\Internal\CoroutineResult
     */
    public static function result($value) {
        return new Internal\CoroutineResult($value);
    }
}