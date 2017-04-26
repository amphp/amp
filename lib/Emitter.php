<?php

namespace Amp;

// @codeCoverageIgnoreStart
try {
    if (!@\assert(false)) {
        development: // PHP 7 development (zend.assertions=1)
        /**
         * Deferred is a container for a stream that can emit values using the emit() method and completed using the
         * complete() and fail() methods of this object. The contained stream may be accessed using the stream() method.
         * This object should not be part of a public API, but used internally to create and emit values from a stream.
         */
        final class Emitter {
            /**
             * @var \Amp\Stream
             */
            private $stream;

            /**
             * @var callable
             */
            private $emit;

            /**
             * @var callable
             */
            private $complete;

            /**
             * @var callable
             */
            private $fail;

            public function __construct() {
                $this->stream = new Internal\PrivateStream(
                    function (callable $emit, callable $complete, callable $fail) {
                        $this->emit = $emit;
                        $this->complete = $complete;
                        $this->fail = $fail;
                    }
                );
            }

            /**
             * @return \Amp\Stream
             */
            public function stream(): Stream {
                return $this->stream;
            }

            /**
             * Emits a value from the stream.
             *
             * @param mixed $value
             *
             * @return \Amp\Promise
             */
            public function emit($value): Promise {
                return ($this->emit)($value);
            }

            /**
             * Completes the stream.
             */
            public function complete() {
                ($this->complete)();
            }

            /**
             * Fails the stream with the given reason.
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
         * An optimized version of Emitter for production environments that is itself the stream. Eval is used to
         * prevent IDEs and other tools from reporting multiple definitions.
         */
        eval('namespace Amp;
        final class Emitter implements Stream {
            use Internal\Producer { emit as public; complete as public; fail as public; }
            public function stream(): Stream { return $this; }
        }');
    }
} catch (\AssertionError $exception) {
    goto development; // zend.assertions=1 and assert.exception=1, use development definition.
} // @codeCoverageIgnoreEnd
