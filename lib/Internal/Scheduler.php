<?php

namespace Amp\Internal;

use Amp\Loop;
use Concurrent\LoopTaskScheduler;

/** @internal */
final class Scheduler extends LoopTaskScheduler
{
    private $dispatch;
    private $watcher;

    public function __construct()
    {
        $this->dispatch = function () {
            $this->watcher = null;
            $this->dispatch();
        };
    }

    protected function activate(): void
    {
        \assert($this->watcher === null);
        $this->watcher = Loop::defer($this->dispatch);
    }

    protected function runLoop(): void
    {
        if ($this->watcher !== null) {
            $this->watcher = Loop::defer($this->dispatch);
        }

        Loop::run();
    }

    protected function stopLoop(): void
    {
        if ($this->watcher !== null) {
            Loop::cancel($this->watcher);
        }

        Loop::stop();
    }
}
