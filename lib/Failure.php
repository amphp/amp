<?php

namespace Amp\Awaitable;

use Interop\Async\Loop;
use Interop\Async\Awaitable;

class Failure implements Awaitable {
    /**
     * @var \Exception|\Throwable $exception
     */
    private $exception;

    /**
     * @param \Throwable|\Exception $exception Rejection reason.
     *
     * @throws \InvalidArgumentException If a non-exception is given.
     */
    public function __construct($exception) {
        if (!$exception instanceof \Throwable && !$exception instanceof \Exception) {
            throw new \InvalidArgumentException("Failure reason must be an exception");
        }

        $this->exception = $exception;
    }

    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved) {
        try {
            $onResolved($this->exception, null);
        } catch (\Throwable $exception) {
            Loop::defer(static function ($watcher, $exception) {
                throw $exception;
            }, $exception);
        } catch (\Exception $exception) {
            Loop::defer(static function ($watcher, $exception) {
                throw $exception;
            }, $exception);
        }
    }
}