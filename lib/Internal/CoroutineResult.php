<?php

namespace Amp\Internal;

/**
 * Used in PHP 5.x to represent coroutine return values. Use the return keyword in PHP 7.x.
 *
 * @internal
 */
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
