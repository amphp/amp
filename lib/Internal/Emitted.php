<?php

namespace Amp\Internal;

use Amp\Future;

final class Emitted {
    /**
     * @var mixed
     */
    private $value;

    /**
     * @var int
     */
    private $waiting;

    /**
     * @var \Amp\Future
     */
    private $ready;

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
        $this->value = $value;
        $this->waiting = (int) $waiting;
        $this->complete = (bool) $complete;
        $this->ready = new Future;

        if ($this->waiting === 0) {
            $this->ready->resolve($this->value);
        }
    }

    /**
     * @return mixed
     */
    public function getValue() {
        return $this->value;
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
            $this->ready->resolve($this->value);
        }
    }

    /**
     * @return \Interop\Async\Awaitable
     */
    public function wait() {
        return $this->ready;
    }
}