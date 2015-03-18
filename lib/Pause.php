<?php

namespace Amp;

class Pause extends Unresolved {
    /**
     * @TODO Add int $msTimeout typehint for PHP7
     */
    public function __construct($msTimeout, Reactor $reactor = null) {
        if ($msTimeout < 1) {
            throw new \DomainException(
                sprintf(
                    "Pause millisecond timeout must be greater than or equal to 1; %d provided",
                    $msTimeout
                )
            );
        }
        if (empty($reactor)) {
            $reactor = getReactor();
        }
        $reactor->once(function() { $this->resolve(); }, $msTimeout);
    }
}
