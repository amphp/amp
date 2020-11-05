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
     * Suspend execution of the fiber. A Continuation object is provided as the first argument to the given callback.
     * The fiber may be resumed with {@see Continuation::resume()} or {@see Continuation::throw()}.
     *
     * @param callable(Fiber):void $enqueue
     * @param FiberScheduler $scheduler
     *
     * @return mixed Value provided to {@see Continuation::resume()}.
     *
     * @throws FiberError Thrown if within {@see FiberScheduler::run()}.
     * @throws Throwable Exception provided to {@see Continuation::throw()}.
     */
    public static function suspend(callable $enqueue, FiberScheduler $scheduler): mixed { }

    /**
     * Private constructor to force use of {@see run()}.
     */
    private function __construct() { }
}
