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
                $this->iterator = new class($this->emit, $this->complete, $this->fail) implements Iterator {
                    use CallableMaker, Internal\Producer;

                    public function __construct(&$emit, &$complete, &$fail) {
                        $emit = $this->callableFromInstanceMethod("emit");
                        $complete = $this->callableFromInstanceMethod("complete");
                        $fail = $this->callableFromInstanceMethod("fail");
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
