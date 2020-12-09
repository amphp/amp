<?php

final class Fiber
{
    /**
     * @param callable $callback Function to invoke when running the fiber.
     *
     * @return self A new Fiber that has not been started.
     */
    public static function create(callable $callback): self { }

    /**
     * Starts execution of the fiber. Returns when the fiber suspends or terminates.
     *
     * Must be called within {@see FiberScheduler::run()}.
     *
     * @param mixed ...$args Arguments passed to fiber function.
     *
     * @throw FiberError If the fiber is running or terminated.
     * @throw Throwable If the fiber callable throws an uncaught exception.
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
     * @throw Throwable If the fiber callable throws an uncaught exception.
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
     * @throw Throwable If the fiber callable throws an uncaught exception.
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
     * Returns the currently executing Fiber instance.
     *
     * Cannot be called within {@see FiberScheduler::run()}.
     *
     * @return self The currently executing fiber.
     *
     * @throws FiberError Thrown if within {@see FiberScheduler::run()}.
     */
    public static function this(): self { }

    /**
     * Suspend execution of the fiber. The fiber may be resumed with {@see Fiber::resume()} or {@see Fiber::throw()}
     * within the run() method of the instance of {@see FiberScheduler} given.
     *
     * Cannot be called within {@see FiberScheduler::run()}.
     *
     * @param FiberScheduler $scheduler
     *
     * @return mixed Value provided to {@see Fiber::resume()}.
     *
     * @throws FiberError Thrown if within {@see FiberScheduler::run()}.
     * @throws Throwable Exception provided to {@see Fiber::throw()}.
     */
    public static function suspend(FiberScheduler $scheduler): mixed { }

    /**
     * Private constructor to force use of {@see create()}.
     */
    private function __construct() { }
}
