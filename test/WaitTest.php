<?php

declare(strict_types=1);

namespace Amp\Test;

use Amp;
use Amp\Deferred;
use Amp\Failure;
use Amp\Pause;
use Amp\Success;
use Interop\Async\Loop;

class WaitTest extends \PHPUnit_Framework_TestCase {
    public function testWaitOnSuccessfulAwaitable()
    {
        $value = 1;

        $awaitable = new Success($value);

        $result = Amp\wait($awaitable);

        $this->assertSame($value, $result);
    }

    public function testWaitOnFailedAwaitable()
    {
        $exception = new \Exception();

        $awaitable = new Failure($exception);

        try {
            $result = Amp\wait($awaitable);
        } catch (\Exception $e) {
            $this->assertSame($exception, $e);
            return;
        }

        $this->fail('Rejection exception should be thrown from wait().');
    }

    /**
     * @depends testWaitOnSuccessfulAwaitable
     */
    public function testWaitOnPendingAwaitable()
    {
        Loop::execute(function () {
            $value = 1;

            $awaitable = new Pause(100, $value);

            $result = Amp\wait($awaitable);

            $this->assertSame($value, $result);
        });
    }

    /**
     * @expectedException \Error
     */
    public function testAwaitableWithNoResolutionPathThrowsException()
    {
        $awaitable = new Deferred;

        $result = Amp\wait($awaitable->getAwaitable());
    }
}
