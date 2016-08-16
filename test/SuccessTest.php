<?php

declare(strict_types=1);

namespace Amp\Test;

use Amp\Success;
use Interop\Async\Awaitable;
use Interop\Async\Loop;

class SuccessTest extends \PHPUnit_Framework_TestCase {
    /**
     * @expectedException \Error
     */
    public function testConstructWithNonException() {
        $failure = new Success($this->getMockBuilder(Awaitable::class)->getMock());
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

            $success = new Success;

            $success->when($callback);
        });

        $this->assertSame(1, $invoked);
    }
}
