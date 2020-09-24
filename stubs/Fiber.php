<?php

final class Fiber
{
    /**
     * Can only be called within {@see FiberScheduler::run()}.
     *
     * @param callable $callback Function to invoke when starting the Fiber.
     * @param mixed ...$args Function arguments.
     */
    public static function run(callable $callback, mixed ...$args): void { }

    /**
     * Private constructor to force use of {@see run()}.
     */
    private function __construct() { }

    /**
     * Suspend execution of the fiber until the given awaitable is resolved.
     *
     * @param Awaitable $awaitable
     * @param FiberScheduler $scheduler
     *
     * @return mixed Resolution value of the awaitable.
     *
     * @throws FiberError Thrown if within {@see FiberScheduler::run()}.
     * @throws Throwable Awaitable failure reason.
     */
    public static function await(Awaitable $awaitable, FiberScheduler $scheduler): mixed { }
}
