<?php

namespace Amp\Awaitable;

use Interop\Async\Awaitable;

try {
    if (@assert(false)) {
        production: // PHP 7 production environment (zend.assertions=0)
        final class Deferred implements Awaitable {
            use Internal\Placeholder {
                resolve as public;
                fail as public;
            }

            /**
             * @return \Interop\Async\Awaitable
             */
            public function getAwaitable() {
                return $this;
            }
        }
    } else {
        development: // PHP 7 development (zend.assertions=1) or PHP 5.x
        final class Deferred {
            /**
             * @var \Interop\Async\Awaitable
             */
            private $awaitable;

            /**
             * @var callable
             */
            private $resolve;

            /**
             * @var callable
             */
            private $fail;

            public function __construct() {
                $this->awaitable = new Internal\PrivateAwaitable(function (callable $resolve, callable $fail) {
                    $this->resolve = $resolve;
                    $this->fail = $fail;
                });
            }

            /**
             * @return \Interop\Async\Awaitable
             */
            public function getAwaitable() {
                return $this->awaitable;
            }

            /**
             * Fulfill the awaitable with the given value.
             *
             * @param mixed $value
             */
            public function resolve($value = null) {
                $resolve = $this->resolve;
                $resolve($value);
            }

            /**
             * Fails the awaitable the the given reason.
             *
             * @param \Throwable|\Exception $reason
             */
            public function fail($reason) {
                $fail = $this->fail;
                $fail($reason);
            }
        }
    }
} catch (\AssertionError $exception) {
    goto development; // zend.assertions=1 and assert.exception=1, use development definition.
}
