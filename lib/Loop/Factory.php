<?php

namespace Amp\Loop;

class Factory {
    /**
     * Creates a new loop instance and chooses the best available driver.
     *
     * @return Driver
     */
    public function create(): Driver {
        if (UvDriver::supported()) {
            return new UvDriver;
        }

        if (EvDriver::supported()) {
            return new EvDriver;
        }

        if (EventDriver::supported()) {
            return new EventDriver;
        }

        return new NativeDriver;
    }
}