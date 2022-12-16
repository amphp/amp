<?php declare(strict_types=1);

namespace Amp;

/**
 * Will be thrown in case an operation is cancelled.
 *
 * @see Cancellation
 * @see DeferredCancellation
 */
class CancelledException extends \Exception
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct("The operation was cancelled", 0, $previous);
    }
}
