<?php

namespace Amp\Loop\Internal;

class Signal extends Watcher
{
    /**
     * @var int
     */
    public $signo;

    /**
     * @param int $signo
     * @param callable $callback
     * @param mixed $data
     */
    public function __construct($signo, callable $callback, $data = null)
    {
        parent::__construct($callback, $data);

        $this->signo = (int) $signo;
    }
}