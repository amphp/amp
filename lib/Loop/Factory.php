<?php

namespace Amp\Loop;

class Factory {
    /**
     * Creates a new loop instance and chooses the best available driver.
     *
     * @return Driver
     */
    public function create(): Driver {
        if (UvLoop::supported()) {
            return new UvLoop;
        }

        if (EvLoop::supported()) {
            return new EvLoop;
        }

        if (EventLoop::supported()) {
            return new EventLoop;
        }

        return new NativeLoop;
    }
}