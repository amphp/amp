<?php

namespace Amp;

class Coroutine {

    /**
     * Return a new function that will be resolved as a coroutine when invoked
     *
     * @param callable $func The callable to be wrapped for coroutine resolution
     * @param \Amp\Reactor $reactor
     * @return callable Returns a wrapped callable
     * @TODO Use variadic function instead of func_get_args() once PHP5.5 is no longer supported
     */
    public static function wrap(callable $func, Reactor $reactor = null) {
        return function() use ($func, $reactor) {
            $result = \call_user_func_array($func, \func_get_args());
            return ($result instanceof \Generator)
                ? self::resolve($result, $reactor)
                : $result;
        };
    }

    /**
     * Create a "return" value for a generator coroutine
     *
     * Prior to PHP7 Generators do not support return expressions. In order to work around
     * this language limitation coroutine authors may yield the result of this function to
     * indicate a coroutine's "return" value in a cross-version-compatible manner.
     *
     * Amp users who want their code to work in both PHP5 and PHP7 environments should yield
     * this function's return value to indicate coroutine results.
     *
     * Example:
     *
     *     // PHP 5 can't use generator return expressions
     *     function() {
     *         $foo = (yield someAsyncThing());
     *         yield Coroutine::result($foo + 42);
     *     };
     *
     *     // PHP 7 doesn't require any extra work:
     *     function() {
     *         $foo = (yield someAsyncThing());
     *         return $foo + 42;
     *     };
     *
     * @param mixed $result The coroutine "return" result
     * @return \Amp\CoroutineResult
     * @TODO This method is only necessary for PHP5; remove once PHP7 is required
     */
    public static function result($result) {
        return new CoroutineResult($result);
    }

    /**
     * Resolve a Generator function as a coroutine
     *
     * Upon resolution the Generator return value is used to succeed the promised result. If an
     * error occurs during coroutine resolution the promise fails.
     *
     * @param \Generator $generator The generator to resolve as a coroutine
     * @param \Amp\Reactor $reactor
     */
    public static function resolve(\Generator $generator, Reactor $reactor = null) {
        $cs = new CoroutineState;
        $cs->reactor = $reactor ?: reactor();
        $cs->promisor = new Deferred;
        $cs->generator = $generator;
        $cs->returnValue = null;
        $cs->currentPromise = null;
        $cs->nestingLevel = 0;

        self::__advance($cs);

        return $cs->promisor->promise();
    }

    private static function __advance(CoroutineState $cs) {
        try {
            $yielded = $cs->generator->current();
            if (!isset($yielded)) {
                if ($cs->generator->valid()) {
                    $cs->reactor->immediately("Amp\Coroutine::__nextTick", ["cb_data" => $cs]);
                } elseif (isset($cs->returnValue)) {
                    $cs->promisor->succeed($cs->returnValue);
                } else {
                    $result = (PHP_MAJOR_VERSION >= 7) ? $cs->generator->getReturn() : null;
                    $cs->promisor->succeed($result);
                }
            } elseif ($yielded instanceof Promise) {
                if ($cs->nestingLevel < 3) {
                    $cs->nestingLevel++;
                    $yielded->when("Amp\Coroutine::__send", $cs);
                    $cs->nestingLevel--;
                } else {
                    $cs->currentPromise = $yielded;
                    $cs->reactor->immediately("Amp\Coroutine::__nextTick", ["cb_data" => $cs]);
                }
            } elseif ($yielded instanceof CoroutineResult) {
                /**
                 * @TODO This block is necessary for PHP5; remove once PHP7 is required and
                 *       we have return expressions in generators
                 */
                $cs->returnValue = $yielded->getReturn();
                self::__send(null, null, $cs);
            } else {
                /**
                 * @TODO Remove CoroutineResult from error message once PHP7 is required
                 */
                $error = new \DomainException(makeGeneratorError($cs->generator, \sprintf(
                    "Unexpected yield (Promise|CoroutineResult|null expected); %s yielded at key %s",
                    \is_object($yielded) ? \get_class($yielded) : \gettype($yielded),
                    $cs->generator->key()
                )));
                $cs->reactor->immediately(function() use ($cs, $error) {
                    $cs->promisor->fail($error);
                });
            }
        } catch (\Throwable $uncaught) {
            /**
             * @codeCoverageIgnoreStart
             * @TODO Remove these coverage ignore lines once PHP7 is required
             */
            $cs->reactor->immediately(function() use ($cs, $uncaught) {
                $cs->promisor->fail($uncaught);
            });
            /**
             * @codeCoverageIgnoreEnd
             */
        } catch (\Exception $uncaught) {
            /**
             * @TODO This extra catch block is necessary for PHP5; remove once PHP7 is required
             */
            $cs->reactor->immediately(function() use ($cs, $uncaught) {
                $cs->promisor->fail($uncaught);
            });
        }
    }

    /**
     * This method is only public for performance reasons. It must not be considered
     * part of the public API and library users should never invoke it directly.
     */
    public static function __nextTick(Reactor $reactor, $watcherId, CoroutineState $cs) {
        if ($cs->currentPromise) {
            $promise = $cs->currentPromise;
            $cs->currentPromise = null;
            $promise->when("Amp\Coroutine::__send", $cs);
        } else {
            self::__send(null, null, $cs);
        }
    }

    /**
     * This method is only public for performance reasons. It must not be considered
     * part of the public API and library users should never invoke it directly.
     */
    public static function __send($error, $result, CoroutineState $cs) {
        try {
            if ($error) {
                $cs->generator->throw($error);
            } else {
                $cs->generator->send($result);
            }
            self::__advance($cs);
        } catch (\Exception $uncaught) {
            $cs->reactor->immediately(function() use ($cs, $uncaught) {
                $cs->promisor->fail($uncaught);
            });
        }
    }
}
