<?php

namespace Amp\Exception;

class TimeoutException extends \RuntimeException {
    /**
     * @param string|null $message
     */
    public function __construct($message = null) {
        parent::__construct($message ?: "Awaitable resolution timed out");
    }
}
