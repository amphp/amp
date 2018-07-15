<?php

namespace Amp\Internal;

use Amp\Loop;
use Concurrent\LoopTaskScheduler;
use Concurrent\TaskScheduler;

TaskScheduler::setDefaultScheduler(new class extends LoopTaskScheduler
{
    private $dispatch;

    public function __construct()
    {
        $this->dispatch = \Closure::fromCallable([$this, 'dispatch']);
    }

    protected function activate()
    {
        Loop::defer($this->dispatch);
    }

    protected function runLoop()
    {
        Loop::run();
    }
});

/**
 * Formats a stacktrace obtained via `debug_backtrace()`.
 *
 * @param array $trace Output of `debug_backtrace()`.
 *
 * @return string Formatted stacktrace.
 *
 * @codeCoverageIgnore
 * @internal
 */
function formatStacktrace(array $trace): string
{
    return \implode("\n", \array_map(function ($e, $i) {
        $line = "#{$i} ";

        if (isset($e["file"])) {
            $line .= "{$e['file']}:{$e['line']} ";
        }

        if (isset($e["type"])) {
            $line .= $e["class"] . $e["type"];
        }

        return $line . $e["function"] . "()";
    }, $trace, \array_keys($trace)));
}

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
