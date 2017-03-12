<?php

namespace Amp\Test;

use Amp\Failure;

class FailureTest extends \PHPUnit\Framework\TestCase {
    /**
     * @expectedException \TypeError
     */
    public function testConstructWithNonException() {
        $failure = new Failure(1);
    }

    public function testWhen() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$reason) {
            ++$invoked;
            $reason = $exception;
        };

        $success = new Failure($exception);

        $success->when($callback);

        $this->assertSame(1, $invoked);
        $this->assertSame($exception, $reason);
    }
}
