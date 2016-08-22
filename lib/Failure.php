<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\Loop;

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
            Loop::defer(static function () use ($exception) {
                throw $exception;
            });
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function subscribe(callable $onNext) {}
}
