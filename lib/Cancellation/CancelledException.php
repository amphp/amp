<?php

namespace Amp\Cancellation;

/**
 * Will be thrown in case an operation is cancelled.
 *
 * @see Token
 * @see CancellationTokenSource
 */
class CancelledException extends \Exception
{
    public function __construct(string $message = "The operation was cancelled", \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
