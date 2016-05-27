<?php

namespace Interop\Async\Loop;

/**
 * State registry to be used by classes implementing the Driver interface.
 */
trait Registry
{
    /**
     * @var array
     */
    private $registry = [];

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
    public function storeState($key, $value)
    {
        if ($value === null) {
            unset($this->registry[$key]);
        } else {
            $this->registry[$key] = $value;
        }
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
    public function fetchState($key)
    {
        return isset($this->registry[$key]) ? $this->registry[$key] : null;
    }
}
