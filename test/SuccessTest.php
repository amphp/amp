<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\Success;
use Amp\Promise;
use React\Promise\RejectedPromise as RejectedReactPromise;

class SuccessTest extends \PHPUnit\Framework\TestCase {
    /**
     * @expectedException \Error
     */
    public function testConstructWithNonException() {
        $failure = new Success($this->getMockBuilder(Promise::class)->getMock());
    }

    public function testOnResolve() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $success = new Success($value);

        $success->onResolve($callback);

        $this->assertSame(1, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testOnResolve
     */
    public function testOnResolveThrowingForwardsToLoopHandlerOnSuccess() {
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

            $success->onResolve($callback);
        });

        $this->assertSame(1, $invoked);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Success
     */
    public function testOnResolveWithReactPromise() {
        Loop::run(function () {
            $success = new Success;
            $success->onResolve(function ($exception, $value) {
                return new RejectedReactPromise(new \Exception("Success"));
            });
        });
    }

    public function testOnResolveWithGenerator() {
        $value = 1;
        $success = new Success($value);
        $invoked = false;
        $success->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        $this->assertTrue($invoked);
    }
}
