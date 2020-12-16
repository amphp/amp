<?php

class ReflectionFiber
{
    /**
     * @param Fiber $fiber Any Fiber object, including those that are not started or have
     *                     terminated.
     */
    public function __construct(Fiber $fiber) { }

    /**
     * @return string Current file of fiber execution.
     */
    public function getExecutingFile(): string { }

    /**
     * @return int Current line of fiber execution.
     */
    public function getExecutingLine(): int { }

    /**
     * @param int $options Same flags as {@see debug_backtrace()}.
     *
     * @return array Fiber backtrace, similar to {@see debug_backtrace()}
     *               and {@see ReflectionGenerator::getTrace()}.
     */
    public function getTrace(int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT): array { }

    /**
     * @return bool True if the fiber has been started.
     */
    public function isStarted(): bool { }

    /**
     * @return bool True if the fiber is currently suspended.
     */
    public function isSuspended(): bool { }

    /**
     * @return bool True if the fiber is currently running.
     */
    public function isRunning(): bool { }

    /**
     * @return bool True if the fiber has completed execution (either returning or
     *              throwing an exception), false otherwise.
     */
    public function isTerminated(): bool { }
}
