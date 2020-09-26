<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;
use function Amp\delay;
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

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Loop stopped without resolving the promise
     */
    public function testPromiseWithNoResolutionPathThrowsException()
    {
        Promise\wait((new Deferred)->promise());
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Loop exceptionally stopped without resolving the promise
     */
    public function testPromiseWithErrorBeforeResolutionThrowsException()
    {
        Loop::defer(function () {
            throw new TestException;
        });

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

    public function testWaitNested()
    {
        $promise = call(static function () {
            yield delay(10);

            return Promise\wait(new Delayed(10, 1));
        });

        $result = Promise\wait($promise);

        $this->assertSame(1, $result);
    }

    public function testWaitNestedDelayed()
    {
        $promise = call(static function () {
            yield delay(10);

            $result = Promise\wait(new Delayed(10, 1));

            yield delay(0);

            return $result;
        });

        $result = Promise\wait($promise);

        $this->assertSame(1, $result);
    }

    public function testWaitNestedConcurrent()
    {
        Loop::defer(function () {
            Promise\wait(new Delayed(100));
        });

        $result = Promise\wait(new Delayed(10, 1));

        $this->assertSame(1, $result);
    }
}
