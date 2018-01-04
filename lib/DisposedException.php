<?php

namespace Amp;

/**
 * Will be thrown in case an operation is cancelled.
 *
 * @see CancellationToken
 * @see CancellationTokenSource
 */
class DisposedException extends \Exception
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct("The flow has been disposed", 0, $previous);
    }
}
