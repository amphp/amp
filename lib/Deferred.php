<?php

namespace Amp;

// @codeCoverageIgnoreStart
try {
    if (!@\assert(false)) {
        development: // PHP 7 development (zend.assertions=1)
        /**
         * Deferred is a container for a promise that is resolved using the resolve() and fail() methods of this object.
         * The contained promise may be accessed using the promise() method. This object should not be part of a public
         * API, but used internally to create and resolve a promise.
         */
        final class Deferred {
            /** @var \Amp\Promise */
            private $promise;

            /** @var callable */
            private $resolve;

            /** @var callable */
            private $fail;

            public function __construct() {
                $this->promise = new class($this->resolve, $this->fail) implements Promise {
                    use CallableMaker, Internal\Placeholder;

                    public function __construct(&$resolve, &$fail) {
                        $resolve = $this->callableFromInstanceMethod("resolve");
                        $fail = $this->callableFromInstanceMethod("fail");
                    }
                };
            }

            /**
             * @return \Amp\Promise
             */
            public function promise(): Promise {
                return $this->promise;
            }

            /**
             * Fulfill the promise with the given value.
             *
             * @param mixed $value
             */
            public function resolve($value = null) {
                ($this->resolve)($value);
            }

            /**
             * Fails the promise the the given reason.
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
         * An optimized version of Deferred for production environments that is itself the promise. Eval is used to
         * prevent IDEs and other tools from reporting multiple definitions.
         */
        eval('namespace Amp;
        final class Deferred implements Promise {
            use Internal\Placeholder { resolve as public; fail as public; }
            public function promise(): Promise { return $this; }
        }');
    }
} catch (\AssertionError $exception) {
    goto development; // zend.assertions=1 and assert.exception=1, use development definition.
} // @codeCoverageIgnoreEnd
