<?php

namespace Interop\Async;

final class Loop
{
    use Registry;

    /**
     * @var LoopDriverFactory
     */
    private static $factory = null;

    /**
     * @var LoopDriver
     */
    private static $driver = null;

    /**
     * @var bool
     */
    private static $running = false;

    /**
     * Set the factory to be used to create a driver if none is passed to
     * self::execute. A default driver will be created if none is running
     * to support synchronous waits in traditional applications.
     */
    public static function setFactory(LoopDriverFactory $factory = null)
    {
        self::$factory = $factory;

        if (!self::$running) {
            self::$driver = self::createDriver();
            self::$registry = [];
        }
    }

    /**
     * Execute a callback within the scope of an event loop driver.
     *
     * @param callable $callback The callback to execute
     * @param LoopDriver $driver The event loop driver
     *
     * @return void
     */
    public static function execute(callable $callback, LoopDriver $driver = null)
    {
        $previousRegistry = self::$registry;
        $previousDriver = self::$driver;

        $driver = $driver ?: self::createDriver();

        self::$driver = $driver;
        self::$registry = [];
        self::$running = true;

        try {
            $callback();

            self::$driver->run();
        } finally {
            self::$driver = $previousDriver;
            self::$registry = $previousRegistry;
            self::$running = false;
        }
    }

    /**
     * Create a new driver if a factory is present, otherwise throw.
     *
     * @throws \LogicException if no factory is set or no driver returned from factory
     */
    private static function createDriver()
    {
        if (self::$factory === null) {
            throw new \LogicException("No loop driver factory set; Either pass a driver to Loop::execute or set a factory.");
        }

        $driver = self::$factory->create();

        if (!$driver instanceof LoopDriver) {
            $type = is_object($driver) ? "an instance of " . get_class($driver) : gettype($driver);
            throw new \LogicException("Loop driver factory returned {$type}, but must return an instance of LoopDriver.");
        }

        return $driver;
    }

    /**
     * Retrieve the event loop driver that is in scope.
     *
     * @return LoopDriver
     */
    public static function get()
    {
        if (null === self::$driver) {
            throw new \RuntimeException('Missing driver; Neither in Loop::execute nor factory set.');
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
     * @param callable(string $watcherId, mixed $data) $callback The callback to defer.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function defer(callable $callback, $data = null)
    {
        return self::get()->defer($callback, $data);
    }

    /**
     * Delay the execution of a callback.
     *
     * The delay is a minimum and approximate, accuracy is not guaranteed.
     *
     * @param int $time The amount of time, in milliseconds, to delay the execution for.
     * @param callable(string $watcherId, mixed $data) $callback The callback to delay.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function delay($time, callable $callback, $data = null)
    {
        return self::get()->delay($time, $callback, $data);
    }

    /**
     * Repeatedly execute a callback.
     *
     * The interval between executions is a minimum and approximate, accuracy is not guaranteed.
     * The first execution is scheduled after the first interval period.
     *
     * @param int $interval The time interval, in milliseconds, to wait between executions.
     * @param callable(string $watcherId, mixed $data) $callback The callback to repeat.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function repeat($interval, callable $callback, $data = null)
    {
        return self::get()->repeat($interval, $callback, $data);
    }

    /**
     * Execute a callback when a stream resource becomes readable.
     *
     * @param resource $stream The stream to monitor.
     * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
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
     * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
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
     * @param callable(string $watcherId, int $signo, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function onSignal($signo, callable $callback, $data = null)
    {
        return self::get()->onSignal($signo, $callback, $data);
    }

    /**
     * Enable a watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public static function enable($watcherId)
    {
        self::get()->enable($watcherId);
    }

    /**
     * Disable a watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public static function disable($watcherId)
    {
        self::get()->disable($watcherId);
    }

    /**
     * Cancel a watcher.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public static function cancel($watcherId)
    {
        self::get()->cancel($watcherId);
    }

    /**
     * Reference a watcher.
     *
     * This will keep the event loop alive whilst the watcher is still being monitored. Watchers have this state by
     * default.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public static function reference($watcherId)
    {
        self::get()->reference($watcherId);
    }

    /**
     * Unreference a watcher.
     *
     * The event loop should exit the run method when only unreferenced watchers are still being monitored. Watchers
     * are all referenced by default.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     */
    public static function unreference($watcherId)
    {
        self::get()->unreference($watcherId);
    }

    /**
     * Set a callback to be executed when an error occurs.
     *
     * Subsequent calls to this method will overwrite the previous handler.
     *
     * @param callable(\Throwable|\Exception $error)|null $callback The callback to execute; null will clear the current handler.
     *
     * @return void
     */
    public static function setErrorHandler(callable $callback = null)
    {
        self::get()->setErrorHandler($callback);
    }

    /**
     * Disable construction as this is a static class.
     */
    private function __construct()
    {
        // intentionally left blank
    }
}
