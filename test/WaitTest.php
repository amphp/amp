<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\call;
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
            yield new Delayed(10);

            return Promise\wait(new Delayed(10, 1));
        });

        $result = Promise\wait($promise);

        $this->assertSame(1, $result);
    }

    public function testWaitNestedDelayed(): void
    {
        $promise = call(static function () {
            yield new Delayed(10);

            $result = Promise\wait(new Delayed(10, 1));

            yield new Delayed(0);

            return $result;
        });

        $result = Promise\wait($promise);

        $this->assertSame(1, $result);
    }
}
