<?php

namespace Amp\Internal;

use Amp\Future;
use Amp\Success;

final class Subscription {
    /**
     * @var callable
     */
    private $unsubscribe;

    /**
     * @var \Amp\Internal\Emitted[]
     */
    private $valueQueue = [];

    /**
     * @var \Amp\Future|null
     */
    private $future;

    /**
     * @var int
     */
    private $current = 0;

    /**
     * @param callable $unsubscribe
     */
    public function __construct(callable $unsubscribe) {
        $this->unsubscribe = $unsubscribe;
    }

    /**
     * Removes the subscription from the emit queue.
     */
    public function unsubscribe() {
        foreach ($this->valueQueue as $emitted) {
            $emitted->ready();
        }

        $this->valueQueue = [];

        $unsubscribe = $this->unsubscribe;
        $unsubscribe($this);
    }

    /**
     * @param \Amp\Internal\Emitted $emitted
     */
    public function push(Emitted $emitted) {
        if ($this->future !== null) {
            $future = $this->future;
            $this->future = null;
            $future->resolve($emitted);
        } else {
            $this->valueQueue[] = $emitted;
        }
    }

    /**
     * @return \Interop\Async\Awaitable
     */
    public function pull() {
        if (empty($this->valueQueue)) {
            $this->future = new Future;
            return $this->future;
        }

        $emitted = $this->valueQueue[$this->current];
        unset($this->valueQueue[$this->current]);
        ++$this->current;

        return new Success($emitted);
    }
}