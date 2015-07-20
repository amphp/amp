<?php

namespace Amp;

interface ExtensionReactor extends Reactor {
    /**
     * React to process control signals
     *
     * @param int $signo The signal number for which to watch
     * @param callable $func A callback to invoke when the specified signal is received
     * @param array $options Watcher options
     * @return string Returns unique (to the process) string watcher ID
     */
    public function onSignal($signo, callable $func, array $options = []);

    /**
     * Retrieve the underlying event loop representation
     *
     * @return mixed
     */
    public function getUnderlyingLoop();
}
