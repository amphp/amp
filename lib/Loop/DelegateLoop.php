<?php

namespace Amp\Loop;

final class DelegateLoop
{
    private $run;
    private $stop;

    public function __construct(callable $run, callable $stop)
    {
        $this->run = $run;
        $this->stop = $stop;
    }

    public function run()
    {
        ($this->run)();
    }

    public function stop()
    {
        ($this->stop)();
    }
}
