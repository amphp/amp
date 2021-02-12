<?php

namespace Amp;

class InvalidAwaitError extends \Error
{
    /**
     * @param mixed $awaited
     * @param \Throwable|null $previous
     */
    public function __construct(mixed $awaited, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf("Fiber must suspend with an instance of %s or an array of such instances; %s awaited; " .
                "use Amp\\await() to suspend a fiber instead of %s::suspend() directly",
                Promise::class,
                \is_object($awaited) ? \get_class($awaited) : \gettype($awaited),
                \Fiber::class,
            ), 0, $previous);
    }
}
