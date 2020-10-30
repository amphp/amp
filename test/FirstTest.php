<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\MultiReasonException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\await;

class FirstTest extends AsyncTestCase
{
    public function testEmptyArray(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("No promises provided");

        Promise\first([]);
    }

    public function testSuccessfulPromisesArray(): void
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $this->assertSame(1, await(Promise\first($promises)));
    }

    public function testFailedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        try {
            await(Promise\first($promises));
        } catch (MultiReasonException $reason) {
            $this->assertSame([$exception, $exception, $exception], $reason->getReasons());
            return;
        }

        $this->fail("Promise was not failed");
    }

    public function testMixedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Success(3)];

        $this->assertSame(3, await(Promise\first($promises)));
    }

    public function testPendingPromiseArray(): void
    {
        $promises = [
            new Delayed(20, 1),
            new Delayed(30, 2),
            new Delayed(10, 3),
        ];

        $this->assertSame(3, await(Promise\first($promises)));

        await(Promise\all($promises)); // Clear event loop.
    }

    public function testNonPromise(): void
    {
        $this->expectException(\TypeError::class);

        Promise\first([1]);
    }
}
