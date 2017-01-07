<?php

namespace Amp;

use AsyncInterop\{ Promise, Promise\ErrorHandler };

/**
 * Creates a successful stream (which is also a promise) using the given value (which can be any value except another
 *  object implementing \AsyncInterop\Promise).
 */
final class Success implements Stream {
    /** @var mixed */
    private $value;

    /**
     * @param mixed $value Anything other than an Promise object.
     *
     * @throws \Error If a promise is given as the value.
     */
    public function __construct($value = null)
    {
        if ($value instanceof Promise) {
            throw new \Error("Cannot use a promise as success value");
        }

        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved) {
        try {
            $onResolved(null, $this->value);
        } catch (\Throwable $exception) {
            ErrorHandler::notify($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listen(callable $onNext) {}
}
