<?php

namespace Amp;

use Amp\Promise\ErrorHandler;

/**
 * Creates a failed stream (which is also a promise) using the given exception.
 */
final class Failure implements Stream {
    /** @var \Throwable $exception */
    private $exception;

    /**
     * @param \Throwable $exception Rejection reason.
     */
    public function __construct(\Throwable $exception) {
        $this->exception = $exception;
    }

    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved) {
        try {
            $onResolved($this->exception, null);
        } catch (\Throwable $exception) {
            ErrorHandler::notify($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listen(callable $onNext) {
    }
}
