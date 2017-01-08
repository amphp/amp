<?php

namespace AsyncInterop;

use AsyncInterop\Loop\Driver;
use AsyncInterop\Loop\DriverFactory;
use AsyncInterop\Loop\InvalidWatcherException;
use AsyncInterop\Loop\UnsupportedFeatureException;

/**
 * Accessor to allow global access to the event loop.
 *
 * @see \AsyncInterop\Loop\Driver
 */
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
     * Setting a factory is only allowed as long as no loop is currently running. Passing null will reset the
     * default driver and remove the factory.
     *
     * The factory will be invoked if none is passed to `Loop::execute`. A default driver will be created to
     * support synchronous waits in traditional applications.
     *
     * @param DriverFactory|null $factory New factory to replace the previous one.
     */
    public static function setFactory(DriverFactory $factory = null)
    {
        if (self::$level > 0) {
            throw new \RuntimeException("Setting a new factory while running isn't allowed!");
        }

        self::$factory = $factory;

        // reset it here, it will be actually instantiated inside execute() or get()
        self::$driver = null;
    }

    /**
     * Execute a callback within the scope of an event loop driver.
     *
     * The loop MUST continue to run until it is either stopped explicitly, no referenced watchers exist anymore, or an
     * exception is thrown that cannot be handled. Exceptions that cannot be handled are exceptions thrown from an
     * error handler or exceptions that would be passed to an error handler but none exists to handle them.
     *
     * @param callable $callback The callback to execute.
     * @param Driver $driver The event loop driver. If `null`, a new one is created from the set factory.
     *
     * @return void
     *
     * @see \AsyncInterop\Loop::setFactory()
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
     * @return Driver
     *
     * @throws \Exception If no factory is set or no driver returned from factory.
     */
    private static function createDriver()
    {
        if (self::$factory === null) {
            throw new \Exception("No loop driver factory set; Either pass a driver to Loop::execute or set a factory.");
        }

        $driver = self::$factory->create();

        if (!$driver instanceof Driver) {
            $type = is_object($driver) ? "an instance of " . get_class($driver) : gettype($driver);
            throw new \Exception("Loop driver factory returned {$type}, but must return an instance of Driver.");
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
     * When an event loop is stopped, it continues with its current tick and exits the loop afterwards. Multiple calls
     * to stop MUST be ignored and MUST NOT raise an exception.
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
     * The deferred callable MUST be executed in the next tick of the event loop and before any other type of watcher.
     * Order of enabling MUST be preserved when executing the callbacks.
     *
     * @param callable(string $watcherId, mixed $data) $callback The callback to defer. The `$watcherId` will be
     *     invalidated before the callback call.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function defer(callable $callback, $data = null)
    {
        $driver = self::$driver ?: self::get();
        return $driver->defer($callback, $data);
    }

    /**
     * Delay the execution of a callback.
     *
     * The delay is a minimum and approximate, accuracy is not guaranteed. Order of calls MUST be determined by which
     * timers expire first, but timers with the same expiration time MAY be executed in any order.
     *
     * @param int $delay The amount of time, in milliseconds, to delay the execution for.
     * @param callable(string $watcherId, mixed $data) $callback The callback to delay. The `$watcherId` will be
     *     invalidated before the callback call.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function delay($time, callable $callback, $data = null)
    {
        $driver = self::$driver ?: self::get();
        return $driver->delay($time, $callback, $data);
    }

    /**
     * Repeatedly execute a callback.
     *
     * The interval between executions is a minimum and approximate, accuracy is not guaranteed. Order of calls MUST be
     * determined by which timers expire first, but timers with the same expiration time MAY be executed in any order.
     * The first execution is scheduled after the first interval period.
     *
     * @param int $interval The time interval, in milliseconds, to wait between executions.
     * @param callable(string $watcherId, mixed $data) $callback The callback to repeat.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function repeat($interval, callable $callback, $data = null)
    {
        $driver = self::$driver ?: self::get();
        return $driver->repeat($interval, $callback, $data);
    }

    /**
     * Execute a callback when a stream resource becomes readable or is closed for reading.
     *
     * Warning: Closing resources locally, e.g. with `fclose`, might not invoke the callback. Be sure to `cancel` the
     * watcher when closing the resource locally. Drivers MAY choose to notify the user if there are watchers on invalid
     * resources, but are not required to, due to the high performance impact. Watchers on closed resources are
     * therefore undefined behavior.
     *
     * Multiple watchers on the same stream MAY be executed in any order.
     *
     * @param resource $stream The stream to monitor.
     * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function onReadable($stream, callable $callback, $data = null)
    {
        $driver = self::$driver ?: self::get();
        return $driver->onReadable($stream, $callback, $data);
    }

    /**
     * Execute a callback when a stream resource becomes writable or is closed for writing.
     *
     * Warning: Closing resources locally, e.g. with `fclose`, might not invoke the callback. Be sure to `cancel` the
     * watcher when closing the resource locally. Drivers MAY choose to notify the user if there are watchers on invalid
     * resources, but are not required to, due to the high performance impact. Watchers on closed resources are
     * therefore undefined behavior.
     *
     * Multiple watchers on the same stream MAY be executed in any order.
     *
     * @param resource $stream The stream to monitor.
     * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the `$data` parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     */
    public static function onWritable($stream, callable $callback, $data = null)
    {
        $driver = self::$driver ?: self::get();
        return $driver->onWritable($stream, $callback, $data);
    }

    /**
     * Execute a callback when a signal is received.
     *
     * Warning: Installing the same signal on different instances of this interface is deemed undefined behavior.
     * Implementations MAY try to detect this, if possible, but are not required to. This is due to technical
     * limitations of the signals being registered globally per process.
     *
     * Multiple watchers on the same signal MAY be executed in any order.
     *
     * @param int $signo The signal number to monitor.
     * @param callable(string $watcherId, int $signo, mixed $data) $callback The callback to execute.
     * @param mixed $data Arbitrary data given to the callback function as the $data parameter.
     *
     * @return string An unique identifier that can be used to cancel, enable or disable the watcher.
     *
     * @throws UnsupportedFeatureException If signal handling is not supported.
     */
    public static function onSignal($signo, callable $callback, $data = null)
    {
        $driver = self::$driver ?: self::get();
        return $driver->onSignal($signo, $callback, $data);
    }

    /**
     * Enable a watcher.
     *
     * Watchers (enabling or new watchers) MUST immediately be marked as enabled, but only be activated (i.e. callbacks
     * can be called) right before the next tick. Callbacks of watchers MUST not be called in the tick they were
     * enabled.
     *
     * @param string $watcherId The watcher identifier.
     *
     * @return void
     *
     * @throws InvalidWatcherException If the watcher identifier is invalid.
     */
    public static function enable($watcherId)
    {
        $driver = self::$driver ?: self::get();
        $driver->enable($watcherId);
    }

    /**
     * Disable a watcher.
     *
     * Disabling a watcher MUST NOT invalidate the watcher. Calling this function MUST NOT fail, even if passed an
     * invalid watcher.
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
     * This will detatch the event loop from all resources that are associated to the watcher. After this operation the
     * watcher is permanently invalid. Calling this function MUST NOT fail, even if passed an invalid watcher.
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
     *
     * @throws InvalidWatcherException If the watcher identifier is invalid.
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
     *
     * @throws InvalidWatcherException If the watcher identifier is invalid.
     */
    public static function unreference($watcherId)
    {
        $driver = self::$driver ?: self::get();
        $driver->unreference($watcherId);
    }

    /**
     * Stores information in the loop bound registry.
     *
     * This can be used to store loop bound information. Stored information is package private. Packages MUST NOT
     * retrieve the stored state of other packages. Packages MUST use the following prefix for keys: `vendor.package.`
     *
     * @param string $key The namespaced storage key.
     * @param mixed $value The value to be stored.
     *
     * @return void
     */
    public static function setState($key, $value)
    {
        $driver = self::$driver ?: self::get();
        $driver->setState($key, $value);
    }

    /**
     * Gets information stored bound to the loop.
     *
     * Stored information is package private. Packages MUST NOT retrieve the stored state of other packages. Packages
     * MUST use the following prefix for keys: `vendor.package.`
     *
     * @param string $key The namespaced storage key.
     *
     * @return mixed The previously stored value or `null` if it doesn't exist.
     */
    public static function getState($key)
    {
        $driver = self::$driver ?: self::get();
        return $driver->getState($key);
    }

    /**
     * Set a callback to be executed when an error occurs.
     *
     * The callback receives the error as the first and only parameter. The return value of the callback gets ignored.
     * If it can't handle the error, it MUST throw the error. Errors thrown by the callback or during its invocation
     * MUST be thrown into the `run` loop and stop the driver.
     *
     * Subsequent calls to this method will overwrite the previous handler.
     *
     * @param callable(\Throwable|\Exception $error)|null $callback The callback to execute. `null` will clear the
     *     current handler.
     *
     * @return callable(\Throwable|\Exception $error)|null The previous handler, `null` if there was none.
     */
    public static function setErrorHandler(callable $callback = null)
    {
        $driver = self::$driver ?: self::get();
        return $driver->setErrorHandler($callback);
    }

    /**
     * Retrieve an associative array of information about the event loop driver.
     *
     * The returned array MUST contain the following data describing the driver's currently registered watchers:
     *
     *     [
     *         "defer"            => ["enabled" => int, "disabled" => int],
     *         "delay"            => ["enabled" => int, "disabled" => int],
     *         "repeat"           => ["enabled" => int, "disabled" => int],
     *         "on_readable"      => ["enabled" => int, "disabled" => int],
     *         "on_writable"      => ["enabled" => int, "disabled" => int],
     *         "on_signal"        => ["enabled" => int, "disabled" => int],
     *         "enabled_watchers" => ["referenced" => int, "unreferenced" => int],
     *     ];
     *
     * Implementations MAY optionally add more information in the array but at minimum the above `key => value` format
     * MUST always be provided.
     *
     * @return array Statistics about the loop in the described format.
     */
    public static function getInfo()
    {
        $driver = self::$driver ?: self::get();
        return $driver->getInfo();
    }

    /**
     * Disable construction as this is a static class.
     */
    private function __construct()
    {
        // intentionally left blank
    }
}
