<?php

namespace Amp\Loop;

// @codeCoverageIgnoreStart
class DriverFactory {
    /**
     * Creates a new loop instance and chooses the best available driver.
     *
     * @return Driver
     */
    public function create(): Driver {
        if (UvDriver::isSupported()) {
            return new UvDriver;
        }

        if (EvDriver::isSupported()) {
            return new EvDriver;
        }

        if (EventDriver::isSupported()) {
            return new EventDriver;
        }

        return new NativeDriver;
    }
}
// @codeCoverageIgnoreEnd