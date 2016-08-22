<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\{ Awaitable, Loop };

/**
 * Creates a successful observable using the given value (which can be any value except another object implementing
 * \Interop\Async\Awaitable).
 */
final class Success implements Observable {
    /** @var mixed */
    private $value;

    /**
     * @param mixed $value Anything other than an Awaitable object.
     *
     * @throws \Error If an awaitable is given as the value.
     */
    public function __construct($value = null)
    {
        if ($value instanceof Awaitable) {
            throw new \Error("Cannot use an awaitable as success value");
        }

        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved) {
        try {
            $onResolved(null, $this->value);
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
