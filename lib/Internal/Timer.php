<?php

namespace Amp\Loop\Internal;

class Timer extends Watcher
{
    /**
     * @var int
     */
    public $interval;

    /**
     * @param int $interval
     * @param callable $callback
     * @param mixed $data
     *
     * @throws \InvalidArgumentException If the interval is <= 0.
     */
    public function __construct($interval, callable $callback, $data = null)
    {
        $interval = (int) $interval;

        if ($interval <= 0) {
            throw new \InvalidArgumentException('Interval must be greater than or equal to zero.');
        }

        parent::__construct($callback, $data);

        $this->interval = $interval;
    }
}