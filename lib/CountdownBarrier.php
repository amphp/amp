<?php

namespace Amp;

use InvalidArgumentException;
use RuntimeException;

/**
 * A countdown barrier returns a promise that is resolved when it was signaled n times.
 *
 * **Example**
 *
 * ```php
 * $countdownBarrier = new \Amp\CountdownBarrier(2);
 * $countdownBarrier->signal();
 * $countdownBarrier->signal(); // promise is now resolved
 * ```
 */
final class CountdownBarrier
{
    /** @var int */
    private $initialCount;
    /** @var int */
    private $currentCount;
    /** @var Deferred */
    private $deferred;

    public function __construct(int $initialCount)
    {
        if ($initialCount < 1) {
            throw new InvalidArgumentException('Counter must be positive');
        }

        $this->initialCount = $initialCount;
        $this->currentCount = $initialCount;
        $this->deferred = new Deferred();
    }

    public function signal(int $signalCount = 1): bool
    {
        if ($signalCount < 1) {
            throw new InvalidArgumentException('Signal count must be greater or equals 1');
        }

        if (0 === $this->currentCount) {
            throw new RuntimeException('CountdownBarrier already resolved');
        }

        $this->currentCount -= $signalCount;

        if (0 === $this->currentCount) {
            $this->deferred->resolve(true);

            return true;
        }

        return false;
    }

    /**
     * @param int $signalCount
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function addCount(int $signalCount = 1)
    {
        if ($signalCount < 1) {
            throw new InvalidArgumentException('Signal count must be greater or equals 1');
        }

        if (0 === $this->currentCount) {
            throw new RuntimeException('CountdownBarrier already resolved');
        }

        $this->currentCount += $signalCount;
    }

    /**
     * @param int $signalCount
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function reset(int $signalCount = null)
    {
        if (null !== $signalCount && $signalCount < 1) {
            throw new InvalidArgumentException('Signal count must be null, greater or equals 1');
        }

        if (0 === $this->currentCount) {
            $this->deferred = new Deferred();
        }

        if (null === $signalCount) {
            $this->currentCount = $this->initialCount;
        } else {
            $this->initialCount = $signalCount;
            $this->currentCount = $this->initialCount;
        }
    }

    /**
     * @return \Amp\Promise
     */
    public function promise(): Promise
    {
        return $this->deferred->promise();
    }
}
