<?php

namespace Interop\Async;

use Interop\Async\Loop\Driver;
use Interop\Async\Loop\DriverFactory;

final class Loop
{
    /**
     * @var DriverFactory
     */
    private static $factory = null;

    /**
     * @var Driver
     */
    private static $driver = null;

    /**
     * @var int
     */
    private static $level = 0;

    /**
     * Set the factory to be used to create a default drivers.
     *
     * Setting a factory is only allowed as long as no loop is currently running.
     * Passing null will reset the default driver and remove the factory.
     *
     * The factory will be invoked if none is passed to Loop::execute. A default driver will be created to support
     * synchronous waits in traditional applications.
     *
     * @param DriverFactory|null $factory
     */
    public static function setFactory(DriverFactory $factory = null)
    {
        if (self::$level > 0) {
            throw new \RuntimeException("Setting a new factory while running isn't allowed!");
        }

        self::$factory = $factory;
        self::$driver = null; // reset it here, it will be actually instantiated inside execute() or get()
    }

    /**
     * Execute a callback within the scope of an event loop driver.
     *
     * @param callable $callback The callback to execute
     * @param Driver $driver The event loop driver. If null a new one is created from the set factory.
     *
     * @return void
     */
    public static function execute(callable $callback, Driver $driver = null)
    {
        $previousDriver = self::$driver;

        self::$driver = $driver ?: self::createDriver();
        self::$level++;

        try {
            self::$driver->defer($callback);
            self::$driver->run();
        } finally {
            self::$driver = $previousDriver;
            self::$level--;
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

        if (!$driver instanceof Driver) {
            $type = is_object($driver) ? "an instance of " . get_class($driver) : gettype($driver);
            throw new \LogicException("Loop driver factory returned {$type}, but must return an instance of Driver.");
        }

        return $driver;
    }

    /**
     * Retrieve the event loop driver that is in scope.
     *
     * @return Driver
     */
    public static function get()
    {
        if (self::$driver) {
            return self::$driver;
        }
        return self::$driver = self::createDriver();
    }

    /**
     * Stop the event loop.
     *
     * @return void
     */
    public static function stop()
    {
        $driver = self::$driver ?: self::get();
        $driver->stop();
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
        $driver = self::$driver ?: self::get();
        return $driver->defer($callback, $data);
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
        $driver = self::$driver ?: self::get();
        return $driver->delay($time, $callback, $data);
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
        $driver = self::$driver ?: self::get();
        return $driver->repeat($interval, $callback, $data);
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
        $driver = self::$driver ?: self::get();
        return $driver->onReadable($stream, $callback, $data);
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
        $driver = self::$driver ?: self::get();
        return $driver->onWritable($stream, $callback, $data);
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
        $driver = self::$driver ?: self::get();
        return $driver->onSignal($signo, $callback, $data);
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
        $driver = self::$driver ?: self::get();
        $driver->enable($watcherId);
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
        $driver = self::$driver ?: self::get();
        $driver->disable($watcherId);
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
        $driver = self::$driver ?: self::get();
        $driver->cancel($watcherId);
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
        $driver = self::$driver ?: self::get();
        $driver->reference($watcherId);
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
        $driver = self::$driver ?: self::get();
        $driver->unreference($watcherId);
    }

    /**
     * Stores information in the loop bound registry. This can be used to store loop bound information. Stored
     * information is package private. Packages MUST NOT retrieve the stored state of other packages.
     *
     * Therefore packages SHOULD use the following prefix to keys: `vendor.package.`
     *
     * @param string $key namespaced storage key
     * @param mixed $value the value to be stored
     *
     * @return void
     */
    public static function storeState($key, $value)
    {
        $driver = self::$driver ?: self::get();
        $driver->storeState($key, $value);
    }

    /**
     * Fetches information stored bound to the loop. Stored information is package private. Packages MUST NOT retrieve
     * the stored state of other packages.
     *
     * Therefore packages SHOULD use the following prefix to keys: `vendor.package.`
     *
     * @param string $key namespaced storage key
     *
     * @return mixed previously stored value or null if it doesn't exist
     */
    public static function fetchState($key)
    {
        $driver = self::$driver ?: self::get();
        return $driver->fetchState($key);
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
        $driver = self::$driver ?: self::get();
        $driver->setErrorHandler($callback);
    }

    /**
     * Retrieve an associative array of information about the event loop driver.
     *
     * The returned array MUST contain the following data describing the driver's
     * currently registered watchers:
     *
     *  [
     *      "defer"         => ["enabled" => int, "disabled" => int],
     *      "delay"         => ["enabled" => int, "disabled" => int],
     *      "repeat"        => ["enabled" => int, "disabled" => int],
     *      "on_readable"   => ["enabled" => int, "disabled" => int],
     *      "on_writable"   => ["enabled" => int, "disabled" => int],
     *      "on_signal"     => ["enabled" => int, "disabled" => int],
     *      "watchers"      => ["referenced" => int, "unreferenced" => int],
     *  ];
     *
     * Implementations MAY optionally add more information in the array but
     * at minimum the above key => value format MUST always be provided.
     *
     * @return array
     */
    public static function info()
    {
        $driver = self::$driver ?: self::get();
        return $driver->info();
    }

    /**
     * Disable construction as this is a static class.
     */
    private function __construct()
    {
        // intentionally left blank
    }
}
