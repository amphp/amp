<?php

namespace Alert;

/**
 * The event reactor is a truly global thing in single-threaded code. Applications should use
 * a single reactor per thread. Accidentally using multiple reactors can lead to all manner of
 * hard-to-debug problems. Should you almost always avoid static and singletons? Yes, and if you
 * abuse this static factory method it's your fault. However, there is nothing wrong with
 * asking for a Reactor instance in your code and using lazy injection via this method if it's
 * not provided.
 *
 * DO NOT instantiate multiple event loops in your PHP application!
 */
class ReactorFactory {
    private static $reactor;

    /**
     * Select a global event reactor based on the current environment
     *
     * @param callable $factory An optional factory callable to generate the shared reactor yourself
     * @return \Alert\Reactor
     */
    public static function select(callable $factory = null) {
        if (self::$reactor) {
            return self::$reactor;
        } elseif ($factory) {
            return self::$reactor = $factory();
        } elseif (extension_loaded('uv')) {
            return self::$reactor = new UvReactor;
        } elseif (extension_loaded('libevent')) {
            return self::$reactor = new LibeventReactor;
        } else {
            return self::$reactor = new NativeReactor;
        }
    }
}
