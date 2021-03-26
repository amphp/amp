<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\asyncValue;
use function Amp\await;

class AnyTest extends AsyncTestCase
{
    public function testEmptyArray()
    {
        self::assertSame([[], []], await(Promise\any([])));
    }

    public function testSuccessfulPromisesArray()
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        self::assertSame([[], [1, 2, 3]], await(Promise\any($promises)));
    }

    public function testFailedPromisesArray()
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        self::assertSame([[$exception, $exception, $exception], []], await(Promise\any($promises)));
    }

    public function testMixedPromisesArray()
    {
        $exception = new \Exception;
        $promises = [new Success(1), new Failure($exception), new Success(3)];

        self::assertSame([[1 => $exception], [0 => 1, 2 => 3]], await(Promise\any($promises)));
    }

    public function testPendingPromiseArray()
    {
        $promises = [
            asyncValue(20, 1),
            asyncValue(30, 2),
            asyncValue(10, 3),
        ];

        self::assertEquals([[], [1, 2, 3]], await(Promise\any($promises)));
    }

    /**
     * @depends testMixedPromisesArray
     */
    public function testArrayKeysPreserved()
    {
        $exception = new \Exception;
        $expected = [['two' => $exception], ['one' => 1, 'three' => 3]];

        $promises = [
            'one' => asyncValue(20, 1),
            'two' => new Failure($exception),
            'three' => asyncValue(10, 3),
        ];

        self::assertEquals($expected, await(Promise\any($promises)));
    }

    public function testNonPromise()
    {
        $this->expectException(\TypeError::class);

        Promise\any([1]);
    }
}
