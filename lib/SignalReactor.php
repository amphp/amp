<?php

namespace Alert;

interface SignalReactor extends Reactor {

    /**
     * React to POSIX-style signals
     *
     * @param int $signal The signal to watch for (e.g. 2 for SIGINT)
     * @param callable $onSignal
     * @return int Returns a unique integer watcher ID
     */
    public function onSignal($signal, callable $onSignal);
}
