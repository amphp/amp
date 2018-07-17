<?php

namespace Amp\Internal;

use Amp\Loop;
use Concurrent\LoopTaskScheduler;
use Concurrent\TaskScheduler;

TaskScheduler::setDefaultScheduler(new class extends LoopTaskScheduler
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

    protected function activate()
    {
        $this->watcher = Loop::defer($this->dispatch);
    }

    protected function runLoop()
    {
        if ($this->watcher !== null) {
            $this->watcher = Loop::defer($this->dispatch);
        }

        Loop::run();
    }

    protected function stopLoop()
    {
        if ($this->watcher !== null) {
            Loop::cancel($this->watcher);
        }

        Loop::stop();
    }
});

/**
 * Creates a `TypeError` with a standardized error message.
 *
 * @param string[] $expected Expected types.
 * @param mixed    $given Given value.
 *
 * @return \TypeError
 *
 * @internal
 */
function createTypeError(array $expected, $given): \TypeError
{
    $givenType = \is_object($given) ? \sprintf("instance of %s", \get_class($given)) : \gettype($given);

    if (\count($expected) === 1) {
        $expectedType = "Expected the following type: " . \array_pop($expected);
    } else {
        $expectedType = "Expected one of the following types: " . \implode(", ", $expected);
    }

    return new \TypeError("{$expectedType}; {$givenType} given");
}
