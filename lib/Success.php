<?php

namespace Amp;

use Interop\Async\Loop;
use Interop\Async\Awaitable;

class Success implements Awaitable {
    /**
     * @var mixed
     */
    private $value;

    /**
     * @param mixed $value Anything other than an Awaitable object.
     *
     * @throws \InvalidArgumentException If an awaitable is given as the value.
     */
    public function __construct($value)
    {
        if ($value instanceof Awaitable) {
            throw new \InvalidArgumentException("Cannot use an awaitable as success value");
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
        } catch (\Exception $exception) {
            Loop::defer(static function () use ($exception) {
                throw $exception;
            });
        }
    }
}
