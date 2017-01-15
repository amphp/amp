<?php

namespace Amp;

final class TimeoutException extends \Exception {
    /**
     * @param string|null $message
     */
    public function __construct(string $message = null) {
        parent::__construct($message ?: "Operation timed out");
    }
}
