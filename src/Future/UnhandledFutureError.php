<?php

namespace Amp\Future;

/**
 * Will be thrown to the event loop error handler in case a future exception is not handled.
 */
final class UnhandledFutureError extends \Error
{
    public function __construct(\Throwable $previous, ?string $origin = null)
    {
        $message = 'Unhandled future: ' . $previous::class . ': "' . $previous->getMessage()
            . '"; Await the Future with Future::await() before the future is destroyed or use '
            . 'Future::ignore() to suppress this exception.';

        if ($origin) {
            $message .= 'The future has been created at ' . $origin;
        }

        parent::__construct($message, 0, $previous);
    }
}
