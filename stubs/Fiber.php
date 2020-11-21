<?php

final class Fiber
{
    /**
     * @param callable $callback Function to invoke when running the fiber.
     */
    public static function create(callable $callback): Fiber { }

    /**
     * Starts execution of the fiber. Returns when the fiber suspends or terminates.
     *
     * Must be called within {@see FiberScheduler::run()}.
     *
     * @param mixed ...$args Arguments passed to fiber function.
     */
    public function start(mixed ...$args): void { }

    /**
     * Resumes the fiber, returning the given value from {@see Fiber::suspend()}.
     * Returns when the fiber suspends or terminates.
     *
     * Must be called within {@see FiberScheduler::run()}.
     *
     * @param mixed $value
     *
     * @throw FiberError If the fiber is running or terminated.
     */
    public function resume(mixed $value = null): void { }

    /**
     * Throws the given exception into the fiber from {@see Fiber::suspend()}.
     * Returns when the fiber suspends or terminates.
     *
     * Must be called within {@see FiberScheduler::run()}.
     *
     * @param Throwable $exception
     *
     * @throw FiberError If the fiber is running or terminated.
     */
    public function throw(Throwable $exception): void { }

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
     * Suspend execution of the fiber. The Fiber object is provided as the first argument to the given callback.
     * The fiber may be resumed with {@see Fiber::resume()} or {@see Fiber::throw()}.
     *
     * Cannot be called within {@see FiberScheduler::run()}.
     *
     * @param callable(Fiber):void $enqueue
     * @param FiberScheduler $scheduler
     *
     * @return mixed Value provided to {@see Fiber::resume()}.
     *
     * @throws FiberError Thrown if within {@see FiberScheduler::run()} or within a callback given to this method.
     * @throws Throwable Exception provided to {@see Fiber::throw()}.
     */
    public static function suspend(callable $enqueue, FiberScheduler $scheduler): mixed { }

    /**
     * Private constructor to force use of {@see create()}.
     */
    private function __construct() { }
}
