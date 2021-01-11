<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;
use function React\Promise\resolve;

class WaitTest extends BaseTest
{
    public function testWaitOnSuccessfulPromise()
    {
        $value = 1;

        $promise = new Success($value);

        $result = Promise\wait($promise);

        $this->assertSame($value, $result);
    }

    public function testWaitOnFailedPromise()
    {
        $exception = new \Exception();

        $promise = new Failure($exception);

        try {
            $result = Promise\wait($promise);
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
        Loop::run(function () {
            $value = 1;

            $promise = new Delayed(100, $value);

            $result = Promise\wait($promise);

            $this->assertSame($value, $result);
        });
    }

    public function testPromiseWithNoResolutionPathThrowsException()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Loop stopped without resolving the promise');

        Promise\wait((new Deferred)->promise());
    }

    public function testPromiseWithErrorBeforeResolutionThrowsException()
    {
        Loop::defer(function () {
            throw new TestException;
        });

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Loop exceptionally stopped without resolving the promise');

        Promise\wait((new Deferred)->promise());
    }

    /**
     * @depends testWaitOnSuccessfulPromise
     */
    public function testReactPromise()
    {
        $value = 1;

        $promise = resolve($value);

        $result = Promise\wait($promise);

        $this->assertSame($value, $result);
    }

    public function testNonPromise()
    {
        $this->expectException(\TypeError::class);
        Promise\wait(42);
    }
}
