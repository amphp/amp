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
