<?php

namespace Amp;

/**
 * Coroutines can yield Pause objects to suspend execution until the specified timeout elapses
 */
class Pause implements Promise {
    use Placeholder;

    /**
     * @param int $timeout The timeout in milliseconds after which the promise will resolve
     * @throws \DomainException On invalid timeout value
     */
    public function __construct($timeout) {
        $timeout = (int) $timeout;
        if ($timeout < 1) {
            throw new \DomainException(
                "Pause timeout must be greater than or equal to 1 millisecond"
            );
        }
        once(function () {
            $this->resolve();
        }, $timeout);
    }
}
