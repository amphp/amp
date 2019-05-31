<?php

namespace Amp\Internal;

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

/**
 * Returns the current time relative to an arbitrary point in time.
 *
 * @return int Time in milliseconds.
 */
function getCurrentTime(): int
{
    static $startTime;

    if (\PHP_INT_SIZE === 4) {
        if ($startTime === null) {
            $startTime = \PHP_VERSION_ID >= 70300 ? \hrtime(false)[0] : \time();
        }

        if (\PHP_VERSION_ID >= 70300) {
            list($seconds, $nanoseconds) = \hrtime(false);
            $seconds -= $startTime;
            return (int) ($seconds * 1000 + $nanoseconds / 1000000);
        }

        return ((\microtime(true) - $startTime) * 1000);
    }

    if (\PHP_VERSION_ID >= 70300) {
        list($seconds, $nanoseconds) = \hrtime(false);
        return (int) ($seconds * 1000 + $nanoseconds / 1000000);
    }

    return (\microtime(true) * 1000);
}
