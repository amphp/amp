<?php

declare(strict_types=1);

namespace Amp;

use Interop\Async\Awaitable;
use Interop\Async\Loop;

/**
 * Creates a failed awaitable using the given exception.
 */
final class Failure implements Awaitable {
    /**
     * @var \Throwable $exception
     */
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
}
