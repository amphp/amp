<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\Promise;

// @codeCoverageIgnoreStart
try {
    if (@\assert(false)) {
        production: // PHP 7 production environment (zend.assertions=0)
        final class Postponed implements Observable {
            use Internal\Producer {
                emit as public;
                resolve as public;
                fail as public;
            }

            /**
             * @return \Amp\Observable
             */
            public function observe(): Observable {
                return $this;
            }
        }
    } else {
        development: // PHP 7 development (zend.assertions=1) or PHP 5.x
        final class Postponed {
            /**
             * @var \Amp\Observable
             */
            private $observable;

            /**
             * @var callable
             */
            private $emit;
    
            /**
             * @var callable
             */
            private $resolve;
            
            /**
             * @var callable
             */
            private $fail;

            public function __construct() {
                $this->observable = new Internal\PrivateObservable(
                    function (callable $emit, callable $resolve, callable $fail) {
                        $this->emit = $emit;
                        $this->resolve = $resolve;
                        $this->fail = $fail;
                    }
                );
            }

            /**
             * @return \Amp\Observable
             */
            public function observe(): Observable {
                return $this->observable;
            }

            /**
             * Emits a value from the observable.
             *
             * @param mixed $value
             *
             * @return \Interop\Async\Promise
             */
            public function emit($value): Promise {
                return ($this->emit)($value);
            }

            /**
             * Resolves the observable with the given value.
             *
             * @param mixed $value
             */
            public function resolve($value = null) {
                ($this->resolve)($value);
            }

            /**
             * Fails the observable with the given reason.
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
