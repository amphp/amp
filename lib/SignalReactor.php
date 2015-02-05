<?php

namespace Amp;

interface SignalReactor extends Reactor {

    /**
     * React to process control signals
     *
     * @param int $signo The signal number to watch for
     * @param callable $onSignal
     * @return int Returns a unique integer watcher ID
     */
    public function onSignal($signo, callable $onSignal);
}
