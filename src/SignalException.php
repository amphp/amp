<?php declare(strict_types=1);

namespace Amp;

/**
 * Used as the previous exception to {@see CancelledException} when a {@see SignalCancellation} is triggered.
 *
 * @see SignalCancellation
 */
class SignalException extends \Exception
{
    /**
     * @param string $message Exception message.
     */
    public function __construct(string $message = "Operation cancelled by signal")
    {
        parent::__construct($message);
    }
}
