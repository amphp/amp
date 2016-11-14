<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\Promise;

// @codeCoverageIgnoreStart
try {
    if (@\assert(false)) {
        production: // PHP 7 production environment (zend.assertions=0)
        final class Deferred implements Promise {
            use Internal\Placeholder {
                resolve as public;
                fail as public;
            }

            /**
             * @return \Interop\Async\Promise
             */
            public function promise(): Promise {
                return $this;
            }
        }
    } else {
        development: // PHP 7 development (zend.assertions=1) or PHP 5.x
        final class Deferred {
            /**
             * @var \Interop\Async\Promise
             */
            private $promise;

            /**
             * @var callable
             */
            private $resolve;

            /**
             * @var callable
             */
            private $fail;

            public function __construct() {
                $this->promise = new Internal\PrivatePromise(function (callable $resolve, callable $fail) {
                    $this->resolve = $resolve;
                    $this->fail = $fail;
                });
            }

            /**
             * @return \Interop\Async\Promise
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
    }
} catch (\AssertionError $exception) {
    goto development; // zend.assertions=1 and assert.exception=1, use development definition.
} // @codeCoverageIgnoreEnd
