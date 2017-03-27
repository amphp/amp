<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Loop;
use React\Promise\RejectedPromise as RejectedReactPromise;

class FailureTest extends \PHPUnit\Framework\TestCase {
    /**
     * @expectedException \TypeError
     */
    public function testConstructWithNonException() {
        $failure = new Failure(1);
    }

    public function testOnResolve() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$reason) {
            ++$invoked;
            $reason = $exception;
        };

        $failure = new Failure($exception);

        $failure->onResolve($callback);

        $this->assertSame(1, $invoked);
        $this->assertSame($exception, $reason);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Success
     */
    public function testOnResolveWithReactPromise() {
        Loop::run(function () {
            $failure = new Failure(new \Exception);
            $failure->onResolve(function ($exception, $value) {
                return new RejectedReactPromise(new \Exception("Success"));
            });
        });
    }

    public function testOnResolveWithGenerator() {
        $exception = new \Exception;
        $failure = new Failure($exception);
        $invoked = false;
        $failure->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $exception;
            yield; // Unreachable, but makes function a generator.
        });

        $this->assertTrue($invoked);
    }
}
