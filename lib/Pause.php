<?php

namespace Amp;

class Pause implements Promise {
    use Placeholder;

    public function __construct($msTimeout, Reactor $reactor = null) {
        if ($msTimeout < 1) {
            throw new \DomainException(sprintf(
                "Pause timeout must be greater than or equal to 1 millisecond; %d provided",
                $msTimeout
            ));
        }
        $reactor = $reactor ?: getReactor();
        $reactor->once(function() { $this->resolve(); }, $msTimeout);
    }
}
