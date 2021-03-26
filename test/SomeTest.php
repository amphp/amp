<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\MultiReasonException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\asyncValue;
use function Amp\await;

class SomeTest extends AsyncTestCase
{
    public function testEmptyArray()
    {
        self::assertSame([[], []], await(Promise\some([], 0)));
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

        self::assertSame([[], [1, 2, 3]], await(Promise\some($promises)));
    }

    public function testFailedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        try {
            await(Promise\some($promises));
        } catch (MultiReasonException $reason) {
            self::assertSame([$exception, $exception, $exception], $reason->getReasons());
            return;
        }

        self::fail("Promise should have failed");
    }

    public function testSuccessfulAndFailedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Success(3)];

        self::assertSame([[0 => $exception, 1 => $exception], [2 => 3]], await(Promise\some($promises)));
    }

    public function testPendingPromiseArray(): void
    {
        $promises = [
            asyncValue(20, 1),
            asyncValue(30, 2),
            asyncValue(10, 3),
        ];

        self::assertEquals([[], [0 => 1, 1 => 2, 2 => 3]], await(Promise\some($promises)));
    }

    public function testArrayKeysPreserved(): void
    {
        $expected = [[], ['one' => 1, 'two' => 2, 'three' => 3]];

        $promises = [
            'one' => asyncValue(20, 1),
            'two' => asyncValue(30, 2),
            'three' => asyncValue(10, 3),
        ];

        self::assertEquals($expected, await(Promise\some($promises)));
    }

    public function testNonPromise(): void
    {
        $this->expectException(\TypeError::class);

        await(Promise\some([1]));
    }
}
