<?php

namespace Interop\Async\EventLoop;

final class Loop
{
    /**
     * @var LoopDriver
     */
    private static $driver = null;

    /**
     * Execute a callback within the scope of an event loop driver.
     *
     * @param callable $callback The callback to execute
     * @param LoopDriver $driver The event loop driver
     *
     * @return void
     */
    public static function execute(callable $callback, LoopDriver $driver)
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
     * @return LoopDriver
     */
    public static function get()
    {
        if (null === self::$driver) {
            throw new \RuntimeException('Not within the scope of an event loop driver');
        }

        return self::$driver;
    }

    /**
     * Stop the event loop.
     *
     * @return void
     */
    public static function stop()
    {
        self::get()->stop();
    }

    /**
     * Defer the execution of a callback.
     *
     * @param callable(mixed $data, string $watcherIdentifier) $callback The callback to defer.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function defer(callable $callback, $data = null)
    {
        return self::get()->defer($callback, $data);
    }

    /**
     * Delay the execution of a callback. The time delay is approximate and accuracy is not guaranteed.
     *
     * @param callable(mixed $data, string $watcherIdentifier) $callback The callback to delay.
     * @param int $time The amount of time, in milliseconds, to delay the execution for.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function delay(callable $callback, $time, $data = null)
    {
        return self::get()->delay($callback, $time, $data);
    }

    /**
     * Repeatedly execute a callback. The interval between executions is approximate and accuracy is not guaranteed.
     *
     * @param callable(mixed $data, string $watcherIdentifier) $callback The callback to repeat.
     * @param int $interval The time interval, in milliseconds, to wait between executions.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function repeat(callable $callback, $interval, $data = null)
    {
        return self::get()->repeat($callback, $interval, $data);
    }

    /**
     * Execute a callback when a stream resource becomes readable.
     *
     * @param resource $stream The stream to monitor.
     * @param callable(resource $stream, mixed $data, string $watcherIdentifier) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function onReadable($stream, callable $callback, $data = null)
    {
        return self::get()->onReadable($stream, $callback, $data);
    }

    /**
     * Execute a callback when a stream resource becomes writable.
     *
     * @param resource $stream The stream to monitor.
     * @param callable(resource $stream, mixed $data, string $watcherIdentifier) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function onWritable($stream, callable $callback, $data = null)
    {
        return self::get()->onWritable($stream, $callback, $data);
    }

    /**
     * Execute a callback when a signal is received.
     *
     * @param int $signo The signal number to monitor.
     * @param callable(int $signo, mixed $data, string $watcherIdentifier) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function onSignal($signo, callable $callback, $data = null)
    {
        return self::get()->onSignal($signo, $callback, $data);
    }

    /**
     * Execute a callback when an error occurs.
     *
     * @param callable(\Throwable|\Exception $exception) $callback The callback to execute.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function onError(callable $callback)
    {
        return self::get()->onError($callback);
    }

    /**
     * Enable a watcher.
     *
     * @param string $watcherIdentifier The watcher identifier.
     *
     * @return void
     */
    public static function enable($watcherIdentifier)
    {
        self::get()->enable($watcherIdentifier);
    }

    /**
     * Disable a watcher.
     *
     * @param string $watcherIdentifier The watcher identifier.
     *
     * @return void
     */
    public static function disable($watcherIdentifier)
    {
        self::get()->disable($watcherIdentifier);
    }

    /**
     * Cancel a watcher.
     *
     * @param string $watcherIdentifier The watcher identifier.
     *
     * @return void
     */
    public static function cancel($watcherIdentifier)
    {
        self::get()->cancel($watcherIdentifier);
    }

    /**
     * Reference a watcher.
     *
     * This will keep the event loop alive whilst the event is still being monitored. Watchers have this state by
     * default.
     *
     * @param string $watcherIdentifier The watcher identifier.
     *
     * @return void
     */
    public static function reference($watcherIdentifier)
    {
        self::get()->reference($watcherIdentifier);
    }

    /**
     * Unreference a watcher.
     *
     * The event loop should exit the run method when only unreferenced watchers are still being monitored. Events are
     * all referenced by default.
     *
     * @param string $watcherIdentifier The watcher identifier.
     *
     * @return void
     */
    public static function unreference($watcherIdentifier)
    {
        self::get()->unreference($watcherIdentifier);
    }

    /**
     * Check whether an optional feature is supported by the current event loop
     * driver.
     *
     * @param int $feature Loop::FEATURE_* constant
     *
     * @return bool
     */
    public static function supports($feature) {
        return self::get()->supports($feature);
    }

    /**
     * Disable construction as this is a static class.
     */
    private function __construct() {}
}
