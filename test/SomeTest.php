<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\MultiReasonException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\await;
use function React\Promise\resolve;

class SomeTest extends AsyncTestCase
{
    public function testEmptyArray()
    {
        $this->assertSame([[], []], Promise\wait(Promise\some([], 0)));
    }

    public function testEmptyArrayWithNonZeroRequired(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Too few promises provided");

        Promise\some([], 1);
    }

    public function testInvalidRequiredNumberOfPromises(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("non-negative");

        Promise\some([], -1);
    }

    public function testSuccessfulPromisesArray(): void
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $this->assertSame([[], [1, 2, 3]], await(Promise\some($promises)));
    }

    public function testReactPromiseArray(): void
    {
        $promises = [resolve(1), resolve(2), resolve(3)];

        $this->assertSame([[], [1, 2, 3]], await(Promise\some($promises)));
    }

    public function testFailedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        try {
            await(Promise\some($promises));
        } catch (MultiReasonException $reason) {
            $this->assertSame([$exception, $exception, $exception], $reason->getReasons());
            return;
        }

        $this->fail("Promise should have failed");
    }

    public function testSuccessfulAndFailedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Success(3)];

        $this->assertSame([[0 => $exception, 1 => $exception], [2 => 3]], await(Promise\some($promises)));
    }

    public function testPendingPromiseArray(): void
    {
        $promises = [
            new Delayed(20, 1),
            new Delayed(30, 2),
            new Delayed(10, 3),
        ];

        $this->assertEquals([[], [0 => 1, 1 => 2, 2 => 3]], await(Promise\some($promises)));
    }

    public function testArrayKeysPreserved(): void
    {
        $expected = [[], ['one' => 1, 'two' => 2, 'three' => 3]];

        $promises = [
            'one' => new Delayed(20, 1),
            'two' => new Delayed(30, 2),
            'three' => new Delayed(10, 3),
        ];


        $this->assertEquals($expected, await(Promise\some($promises)));
    }

    public function testNonPromise(): void
    {
        $this->expectException(\TypeError::class);

        await(Promise\some([1]));
    }
}
