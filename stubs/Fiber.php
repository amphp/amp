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
     * @return bool True if the fiber is suspended.
     */
    public function isSuspended(): bool { }

    /**
     * @return bool True if the fiber is currently running.
     */
    public function isRunning(): bool { }

    /**
     * @return bool True if the fiber has completed execution.
     */
    public function isTerminated(): bool { }

    /**
     * Resumes the fiber, returning the given value from {@see Fiber::suspend()}.
     *
     * @param mixed $value
     */
    public function resume(mixed $value = null): void { }

    /**
     * Throws the given exception into the fiber from {@see Fiber::suspend()}.
     *
     * @param Throwable $exception
     */
    public function throw(Throwable $exception): void { }

    /**
     * Suspend execution of the fiber. The Fiber object is provided as the first argument to the given callback.
     * The fiber may be resumed with {@see Fiber::resume()} or {@see Fiber::throw()}.
     *
     * @param callable(Fiber):void $enqueue
     * @param FiberScheduler $scheduler
     *
     * @return mixed Value provided to {@see Fiber::resume()}.
     *
     * @throws FiberError Thrown if within {@see FiberScheduler::run()}.
     * @throws Throwable Exception provided to {@see Fiber::throw()}.
     */
    public static function suspend(callable $enqueue, FiberScheduler $scheduler): mixed { }
}
