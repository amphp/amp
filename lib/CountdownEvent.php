<?php

namespace Amp;

use InvalidArgumentException;
use RuntimeException;

/**
 * A countdown event is a promise that is resolved when it was signaled n times
 *
 * **Example**
 *
 * ```php
 * $countdownEvent = new CountdownEvent(2);
 * $countdownEvent->signal();
 * $countdownEvent->signal(); // promise is now resolved
 * ```
 */
final class CountdownEvent implements Promise
{
    /** @var int */
    private $counter;
    /** @var Deferred */
    private $deferred;

    public function __construct(int $counter)
    {
        if ($counter < 1) {
            throw new InvalidArgumentException('Counter must be positive');
        }

        $this->counter = $counter;
        $this->deferred = new Deferred();
    }

    /**
     * @return void
     */
    public function signal()
    {
        if (0 === $this->counter) {
            throw new RuntimeException('CountdownEvent already resolved');
        }

        --$this->counter;

        if (0 === $this->counter) {
            $this->deferred->resolve(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onResolve(callable $onResolved)
    {
        $this->deferred->promise()->onResolve($onResolved);
    }
}
