<?php

namespace Amp;

class Pause extends PrivatePlaceholder {
    public function __construct(int $millisecondTimeout, Reactor $reactor = null) {
        if ($millisecondTimeout < 1) {
            throw new \DomainException(
                sprintf(
                    "Pause millisecond timeout must be greater than or equal to 1; %d provided",
                    $millisecondTimeout
                )
            );
        }
        if (empty($reactor)) {
            $reactor = getReactor();
        }
        $reactor->once(function() { $this->resolve(); }, $millisecondTimeout);
    }
}
