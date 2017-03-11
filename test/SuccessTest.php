<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\Success;
use Amp\Promise;

class SuccessTest extends \PHPUnit\Framework\TestCase {
    /**
     * @expectedException \Error
     */
    public function testConstructWithNonException() {
        $failure = new Success($this->getMockBuilder(Promise::class)->getMock());
    }

    public function testWhen() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $success = new Success($value);

        $success->when($callback);

        $this->assertSame(1, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testWhen
     */
    public function testWhenThrowingForwardsToLoopHandlerOnSuccess() {
        Loop::run(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $success = new Success;

            $success->when($callback);
        });

        $this->assertSame(1, $invoked);
    }
}
