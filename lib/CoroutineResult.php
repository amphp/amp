<?php

namespace Amp;

/**
 * Create a "return" value for a generator coroutine
 *
 * Prior to PHP7 Generators do not support return expressions. In order to work around
 * this language limitation amp coroutines can yield the result of this function to
 * indicate a coroutine's "return" value in a legacy-compatible way.
 *
 * Amp users who want their code to work in both PHP5 and PHP7 environments should yield
 * this object to indicate coroutine results.
 *
 * Example:
 *
 *     // PHP 5 can't use generator return expressions
 *     function() {
 *         $foo = (yield someAsyncThing());
 *         yield new Amp\CoroutineResult($foo + 42);
 *     };
 *
 *     // PHP 7 doesn't require any extra work:
 *     function() {
 *         $foo = yield someAsyncThing();
 *         return $foo + 42;
 *     };
 *
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
