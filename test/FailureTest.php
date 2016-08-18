<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp\Failure;
use Interop\Async\Loop;

class FailureTest extends \PHPUnit_Framework_TestCase {
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

    /**
     * @depends testWhen
     */
    public function testWhenThrowingForwardsToLoopHandlerOnSuccess() {
        Loop::execute(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $success = new Failure(new \Exception);

            $success->when($callback);
        });

        $this->assertSame(1, $invoked);
    }
}
