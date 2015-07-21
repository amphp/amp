<?php

namespace Amp;

class Pause implements Promise {
    use Placeholder;

    /**
     * @param int $timeout The timeout in milliseconds after which the promise will resolve
     * @param \Amp\Reactor $reactor An optional reactor instance (default reactor used if not specified)
     * @throws \DomainException If
     */
    public function __construct($timeout, Reactor $reactor = null) {
        $timeout = (int) $timeout;
        if ($timeout < 1) {
            throw new \DomainException(
                "Pause timeout must be greater than or equal to 1 millisecond"
            );
        }
        $reactor = $reactor ?: reactor();
        $reactor->once(function() {
            $this->resolve();
        }, $timeout);
    }
}
