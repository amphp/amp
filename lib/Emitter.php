<?php

namespace Amp;

// @codeCoverageIgnoreStart
try {
    if (!@\assert(false)) {
        development: // PHP 7 development (zend.assertions=1)
        /**
         * Deferred is a container for an iterator that can emit values using the emit() method and completed using the
         * complete() and fail() methods of this object. The contained iterator may be accessed using the iterate()
         * method. This object should not be part of a public API, but used internally to create and emit values to an
         * iterator.
         */
        final class Emitter {
            /** @var \Amp\Iterator */
            private $iterator;

            /** @var callable */
            private $emit;

            /** @var callable */
            private $complete;

            /** @var callable */
            private $fail;

            public function __construct() {
                $this->iterator = new class (function (callable $emit, callable $complete, callable $fail) {
                    $this->emit = $emit;
                    $this->complete = $complete;
                    $this->fail = $fail;
                }) implements Iterator {
                    use CallableMaker, Internal\Producer;

                    /**
                     * @param callable (callable $emit, callable $complete, callable $fail): void $producer
                     */
                    public function __construct(callable $producer) {
                        $producer(
                            $this->callableFromInstanceMethod("emit"),
                            $this->callableFromInstanceMethod("complete"),
                            $this->callableFromInstanceMethod("fail")
                        );
                    }
                };
            }

            /**
             * @return \Amp\Iterator
             */
            public function iterate(): Iterator {
                return $this->iterator;
            }

            /**
             * Emits a value to the iterator.
             *
             * @param mixed $value
             *
             * @return \Amp\Promise
             */
            public function emit($value): Promise {
                return ($this->emit)($value);
            }

            /**
             * Completes the iterator.
             */
            public function complete() {
                ($this->complete)();
            }

            /**
             * Fails the iterator with the given reason.
             *
             * @param \Throwable $reason
             */
            public function fail(\Throwable $reason) {
                ($this->fail)($reason);
            }
        }
    } else {
        production: // PHP 7 production environment (zend.assertions=0)
        /**
         * An optimized version of Emitter for production environments that is itself the iterator. Eval is used to
         * prevent IDEs and other tools from reporting multiple definitions.
         */
        eval('namespace Amp;
        final class Emitter implements Iterator {
            use Internal\Producer { emit as public; complete as public; fail as public; }
            public function iterate(): Iterator { return $this; }
        }');
    }
} catch (\AssertionError $exception) {
    goto development; // zend.assertions=1 and assert.exception=1, use development definition.
} // @codeCoverageIgnoreEnd
