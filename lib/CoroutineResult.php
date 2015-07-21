<?php

namespace Amp;

/**
 * @TODO This class is only necessary for PHP5; remove once PHP7 is required
 */
class CoroutineResult {
    private $result;

    /**
     * @param mixed $result
     */
    public function __construct($result) {
        $this->result = $result;
    }

    /**
     * Retrieve the coroutine return result
     *
     * @return mixed
     */
    public function getReturn() {
        return $this->result;
    }
}
