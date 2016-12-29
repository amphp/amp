<?php

namespace Amp;

use Interop\Async\Promise\ErrorHandler;

/**
 * Creates a failed observable using the given exception.
 */
final class Failure implements Observable {
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
    public function subscribe(callable $onNext) {}
}
