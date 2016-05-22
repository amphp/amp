<?php

namespace Interop\Async;

/**
 * State registry to be used in Interop\Async\Loop.
 *
 * THIS TRAIT SHOULD NOT BE USED BY LOOP DRIVERS. It's the responsibility of the
 * loop accessor to manage this state.
 */
trait Registry
{
    /**
     * @var array
     */
    private static $registry = null;

    /**
     * Stores information in the loop bound registry. This can be used to store
     * loop bound information. Stored information is package private.
     * Packages MUST NOT retrieve the stored state of other packages.
     *
     * Therefore packages SHOULD use the following prefix to keys:
     * `vendor.package.`
     *
     * @param string $key namespaced storage key
     * @param mixed $value the value to be stored
     *
     * @return void
     */
    public static function storeState($key, $value)
    {
        if (self::$registry === null) {
            throw new \RuntimeException('Not within the scope of an event loop driver');
        }

        if ($value === null) {
            unset(self::$registry[$key]);
        } else {
            self::$registry[$key] = $value;
        }
    }

    /**
     * Fetches information stored bound to the loop. Stored information is
     * package private. Packages MUST NOT retrieve the stored state of
     * other packages.
     *
     * Therefore packages SHOULD use the following prefix to keys:
     * `vendor.package.`
     *
     * @param string $key namespaced storage key
     *
     * @return mixed previously stored value or null if it doesn't exist
     */
    public static function fetchState($key)
    {
        if (self::$registry === null) {
            throw new \RuntimeException('Not within the scope of an event loop driver');
        }

        return isset(self::$registry[$key]) ? self::$registry[$key] : null;
    }
}
