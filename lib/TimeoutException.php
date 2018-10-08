<?php

namespace Amp;

/**
 * Thrown if an awaitable doesn't resolve within a specified timeout.
 *
 * @see \Amp\timeout()
 */
class TimeoutException extends \Exception
{
    /**
     * @param string|null $message Exception message.
     */
    public function __construct(string $message = "Operation timed out")
    {
        parent::__construct($message);
    }
}
