<?php

namespace Amp;

class ReactorFactory {
    private static $reactor;

    /**
     * This method is deprecated. New code should use Amp\getReactor() instead.
     */
    public static function select(callable $factory = null) {
        trigger_error(
            'Amp\\ReactorFactory is deprecated and scheduled for removal. ' .
            'Please update code to use the Amp\\getReactor() function instead.',
            E_USER_DEPRECATED
        );

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
