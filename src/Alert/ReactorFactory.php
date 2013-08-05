<?php

namespace Alert;

class ReactorFactory {

    private $hasExtLibevent;

    function __construct() {
        $this->hasExtLibevent = extension_loaded('libevent');
    }

    function __invoke() {
        return $this->select();
    }

    function select() {
        if ($this->hasExtLibevent) {
            $reactor = new LibeventReactor;
        } else {
            $reactor = new NativeReactor;
        }

        return $reactor;
    }

}
