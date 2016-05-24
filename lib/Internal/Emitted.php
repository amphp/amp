<?php

namespace Amp\Internal;

use Amp\Future;
use Interop\Async\Awaitable;

final class Emitted {
    /**
     * @var \Amp\Future
     */
    private $future;

    /**
     * @var \Interop\Async\Awaitable
     */
    private $awaitable;

    /**
     * @var int
     */
    private $waiting = 0;

    /**
     * @param \Interop\Async\Awaitable $awaitable
     */
    public function __construct(Awaitable $awaitable) {
        $this->awaitable = $awaitable;
        $this->future = new Future;
    }

    /**
     * @return \Interop\Async\Awaitable
     */
    public function getAwaitable() {
        ++$this->waiting;
        return $this->awaitable;
    }

    /**
     * Notifies the placeholder that the consumer is ready.
     */
    public function ready() {
        if (0 === --$this->waiting) {
            $this->future->resolve();
        }
    }

    /**
     * Returns an awaitable that is fulfilled once all consumers are ready.
     *
     * @return \Interop\Async\Awaitable
     */
    public function wait() {
        return $this->future;
    }
}
