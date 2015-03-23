<?php

namespace Amp;

interface SignalReactor extends Reactor {
    /**
     * React to process control signals
     *
     * @param int $signo The signal number for which to watch
     * @param callable $func A callback to invoke when the specified signal is received
     * @return string Returns unique (to the process) string watcher ID
     */
    public function onSignal(int $signo, callable $func): string;
}
