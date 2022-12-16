<?php declare(strict_types=1);

namespace Amp;

/**
 * Used as the previous exception to {@see CancelledException} when a {@see TimeoutCancellation} expires.
 *
 * @see TimeoutCancellation
 */
class TimeoutException extends \Exception
{
    /**
     * @param string $message Exception message.
     */
    public function __construct(string $message = "Operation timed out")
    {
        parent::__construct($message);
    }
}
