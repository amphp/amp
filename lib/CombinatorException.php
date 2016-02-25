<?php

namespace Amp;

/**
 * CombinatorException is always thrown if multiple promises are combined by combinator functions
 * and an exception is thrown.
 */
class CombinatorException extends \RuntimeException {
    private $exceptions;

    /**
     * @param string $message detailed exception message
     * @param array  $exceptions combined exceptions
     */
    public function __construct($message, array $exceptions = []) {
        parent::__construct($message, 0, null);
        $this->exceptions = $exceptions;
    }

    public function getExceptions() {
        return $this->exceptions;
    }
}
