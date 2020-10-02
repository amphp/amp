<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;
use function Amp\delay;
use function React\Promise\resolve;

class WaitTest extends AsyncTestCase
{
    public function testWaitOnSuccessfulPromise(): void
    {
        $value = 1;

        $promise = new Success($value);

        $result = Promise\wait($promise);

        $this->assertSame($value, $result);
    }

    public function testWaitOnFailedPromise(): void
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
    public function testWaitOnPendingPromise(): void
    {
        $value = 1;

        $promise = new Delayed(100, $value);

        $result = Promise\wait($promise);

        $this->assertSame($value, $result);
    }

    public function testPromiseWithNoResolutionPathThrowsException(): void
    {
        $this->expectException(\FiberError::class);
        $this->expectExceptionMessage("Scheduler ended");

        Promise\wait((new Deferred)->promise());
    }

    /**
     * @depends testWaitOnSuccessfulPromise
     */
    public function testReactPromise(): void
    {
        $value = 1;

        $promise = resolve($value);

        $result = Promise\wait($promise);

        $this->assertSame($value, $result);
    }

    public function testWaitNested(): void
    {
        $promise = call(static function () {
            yield delay(10);

            return Promise\wait(new Delayed(10, 1));
        });

        $result = Promise\wait($promise);

        $this->assertSame(1, $result);
    }

    public function testWaitNestedDelayed(): void
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
}
