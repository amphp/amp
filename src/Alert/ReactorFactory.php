<?php

namespace Alert;

class ReactorFactory {

    private $hasExtLibevent;

    public function __construct() {
        $this->hasExtLibevent = extension_loaded('libevent');
    }

    public function __invoke() {
        return $this->select();
    }

    public function select() {
        if ($this->hasExtLibevent) {
            $reactor = new LibeventReactor;
        } else {
            $reactor = new NativeReactor;
        }

        return $reactor;
    }

}
