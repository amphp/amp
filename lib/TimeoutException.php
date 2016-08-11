<?php

namespace Amp;

class TimeoutException extends \Exception {
    /**
     * @param string|null $message
     */
    public function __construct(string $message = null) {
        parent::__construct($message ?: "Awaitable resolution timed out");
    }
}
