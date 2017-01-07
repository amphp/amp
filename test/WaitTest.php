<?php

namespace Amp\Test;

use Amp;
use Amp\{ Deferred, Failure, Pause, Success };
use AsyncInterop\Loop;

class WaitTest extends \PHPUnit_Framework_TestCase {
    public function testWaitOnSuccessfulPromise()
    {
        $value = 1;

        $promise = new Success($value);

        $result = Amp\wait($promise);

        $this->assertSame($value, $result);
    }

    public function testWaitOnFailedPromise()
    {
        $exception = new \Exception();

        $promise = new Failure($exception);

        try {
            $result = Amp\wait($promise);
        } catch (\Exception $e) {
            $this->assertSame($exception, $e);
            return;
        }

        $this->fail('Rejection exception should be thrown from wait().');
    }

    /**
     * @depends testWaitOnSuccessfulPromise
     */
    public function testWaitOnPendingPromise()
    {
        Loop::execute(function () {
            $value = 1;

            $promise = new Pause(100, $value);

            $result = Amp\wait($promise);

            $this->assertSame($value, $result);
        });
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Loop stopped without resolving promise
     */
    public function testPromiseWithNoResolutionPathThrowsException()
    {
        $promise = new Deferred;

        $result = Amp\wait($promise->promise());
    }
}
