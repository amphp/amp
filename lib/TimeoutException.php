<?php declare(strict_types = 1);

namespace Amp;

class TimeoutException extends \Exception {
    /**
     * @param string|null $message
     */
    public function __construct(string $message = null) {
        parent::__construct($message ?: "Promise resolution timed out");
    }
}
