<?php

namespace Interop\Async\EventLoop;

final class EventLoop
{
    /**
     * @var EventLoopDriver
     */
    private static $driver = null;

    /**
     * Execute a callback within the scope of an event loop driver.
     *
     * @param callable $callback The callback to execute
     * @param EventLoopDriver $driver The event loop driver
     *
     * @return void
     */
    public static function execute(callable $callback, EventLoopDriver $driver)
    {
        $previousDriver = self::$driver;

        self::$driver = $driver;

        try {
            $callback();

            self::$driver->run();
        } finally {
            self::$driver = $previousDriver;
        }
    }

    /**
     * Retrieve the event loop driver that is in scope.
     * 
     * @return EventLoopDriver
     */
    public static function get()
    {
        self::inScope();

        return self::$driver;
    }

    /**
     * Stop the event loop.
     * 
     * @return void
     */
    public static function stop()
    {
        self::inScope();

        self::$driver->stop();
    }

    /**
     * Defer the execution of a callback.
     * 
     * @param callable $callback The callback to defer.
     * 
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public static function defer(callable $callback)
    {
        self::inScope();

        return self::$driver->defer($callback);
    }

    /**
     * Delay the execution of a callback. The time delay is approximate and accuracy is not guaranteed.
     * 
     * @param callable $callback The callback to delay.
     * @param float $time The amount of time, in seconds, to delay the execution for.
     * 
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public static function delay(callable $callback, float $time)
    {
        self::inScope();

        return self::$driver->delay($callback, $time);
    }

    /**
     * Repeatedly execute a callback. The interval between executions is approximate and accuracy is not guaranteed.
     * 
     * @param callable $callback The callback to repeat.
     * @param float $interval The time interval, in seconds, to wait between executions.
     * 
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public static function repeat(callable $callback, float $interval)
    {
        self::inScope();

        return self::$driver->repeat($callback, $interval);
    }

    /**
     * Execute a callback when a stream resource becomes readable.
     * 
     * @param resource $stream The stream to monitor.
     * @param callable $callback The callback to execute.
     * 
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public static function onReadable($stream, callable $callback)
    {
        self::inScope();

        return self::$driver->onReadable($stream, $callback);
    }

    /**
     * Execute a callback when a stream resource becomes writable.
     * 
     * @param resource $stream The stream to monitor.
     * @param callable $callback The callback to execute.
     * 
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public static function onWritable($stream, callable $callback)
    {
        self::inScope();

        return self::$driver->onWritable($stream, $callback);
    }

    /**
     * Execute a callback when a signal is received.
     * 
     * @param int $signo The signal number to monitor.
     * @param callable $callback The callback to execute.
     * 
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public static function onSignal(int $signo, callable $callback)
    {
        self::inScope();

        return self::$driver->onSignal($signo, $callback);
    }

    /**
     * Execute a callback when an error occurs.
     * 
     * @param callable $callback The callback to execute.
     * 
     * @return string An identifier that can be used to cancel, enable or disable the event.
     */
    public static function onError(callable $callback)
    {
        self::inScope();

        return self::$driver->onError($callback);
    }

    /**
     * Enable an event.
     * 
     * @param string $eventIdentifier The event identifier.
     * 
     * @return void
     */
    public static function enable(string $eventIdentifier)
    {
        self::inScope();

        self::$driver->enable($eventIdentifier);
    }

    /**
     * Disable an event.
     * 
     * @param string $eventIdentifier The event identifier.
     * 
     * @return void
     */
    public static function disable(string $eventIdentifier)
    {
        self::inScope();

        self::$driver->disable($eventIdentifier);
    }

    /**
     * Cancel an event.
     * 
     * @param string $eventIdentifier The event identifier.
     * 
     * @return void
     */
    public static function cancel(string $eventIdentifier)
    {
        self::inScope();

        self::$driver->cancel($eventIdentifier);
    }

    /**
     * Reference an event.
     * 
     * This will keep the event loop alive whilst the event is still being monitored. Events have this state by default.
     * 
     * @param string $eventIdentifier The event identifier.
     * 
     * @return void
     */
    public static function reference(string $eventIdentifier)
    {
        self::inScope();

        self::$driver->reference($eventIdentifier);
    }

    /**
     * Unreference an event.
     * 
     * The event loop should exit the run method when only unreferenced events are still being monitored. Events are all
     * referenced by default.
     * 
     * @param string $eventIdentifier The event identifier.
     * 
     * @return void
     */
    public static function unreference(string $eventIdentifier)
    {
        self::inScope();

        self::$driver->unreference($eventIdentifier);
    }

    /**
     * Validate that the event loop is currently within the scope of a driver.
     * 
     * @return void
     */
    private static function inScope()
    {
        if (null === self::$driver) {
            throw new \RuntimeException('Not within the scope of an event loop driver');
        }
    }

    /**
     * Disable construction as this is a static class.
     */
    public function __construct()
    {
        throw new \LogicException('This class is a static class and should not be initialized');
    }
}
