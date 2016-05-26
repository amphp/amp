<?php

namespace Amp\Internal;

use Amp\Future;
use Amp\Success;
use Interop\Async\Awaitable;

final class Emitted {
    /**
     * @var \Interop\Async\Awaitable
     */
    private $awaitable;

    /**
     * @var int
     */
    private $waiting;

    /**
     * @var \Amp\Future
     */
    private $future;

    /**
     * @var bool
     */
    private $complete;

    /**
     * @param mixed $value
     * @param int $waiting
     * @param bool $complete
     */
    public function __construct($value, $waiting, $complete) {
        $this->awaitable = $value instanceof Awaitable ? $value : new Success($value);
        $this->waiting = (int) $waiting;
        $this->complete = (bool) $complete;
        $this->future = new Future;
    }

    /**
     * @return \Interop\Async\Awaitable|mixed
     */
    public function getAwaitable() {
        return $this->awaitable;
    }

    /**
     * @return bool
     */
    public function isComplete() {
        return $this->complete;
    }

    /**
     * Indicates that a subscriber has consumed the value represented by this object.
     */
    public function ready() {
        if (--$this->waiting === 0) {
            $this->future->resolve($this->awaitable);
        }
    }

    /**
     * @return \Interop\Async\Awaitable
     */
    public function wait() {
        return $this->future;
    }
}