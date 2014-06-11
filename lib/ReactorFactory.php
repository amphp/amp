<?php

namespace Alert;

class ReactorFactory {
    public function __invoke() {
        return $this->select();
    }

    public function select() {
        if (extension_loaded('uv')) {
            $reactor = new UvReactor;
        } elseif (extension_loaded('libevent')) {
            $reactor = new LibeventReactor;
        } else {
            $reactor = new NativeReactor;
        }

        return $reactor;
    }
}
