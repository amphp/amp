<?php

namespace Amp\Loop;

use Interop\Async\Loop\DriverFactory;

/**
 * Default loop factory for Amp.
 */
class LoopFactory implements DriverFactory {
    /**
     * {@inheritdoc}
     */
    public function create() {
        if (EvLoop::enabled()) {
            return new EvLoop();
        }

        return new NativeLoop();
    }
}
