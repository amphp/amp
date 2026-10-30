<?php declare(strict_types=1);

namespace Amp;

/**
 * Will be thrown in case an operation is cancelled.
 *
 * @see Cancellation
 * @see DeferredCancellation
 *
 * @api
 */
class CancelledException extends \Exception
{
    /** @psalm-capabilities read-props */
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct("The operation was cancelled", 0, $previous);
    }
}
