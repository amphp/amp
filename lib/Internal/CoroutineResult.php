<?php

namespace Amp\Internal;

class CoroutineResult
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * @param mixed $value Coroutine return value.
     */
    public function __construct($value) {
        $this->value = $value;
    }

    /**
     * @return mixed Coroutine return value.
     */
    public function getValue() {
        return $this->value;
    }
}
