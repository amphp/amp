<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\await;

class AnyTest extends AsyncTestCase
{
    public function testEmptyArray()
    {
        $this->assertSame([[], []], await(Promise\any([])));
    }

    public function testSuccessfulPromisesArray()
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $this->assertSame([[], [1, 2, 3]], await(Promise\any($promises)));
    }

    public function testFailedPromisesArray()
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        $this->assertSame([[$exception, $exception, $exception], []], await(Promise\any($promises)));
    }

    public function testMixedPromisesArray()
    {
        $exception = new \Exception;
        $promises = [new Success(1), new Failure($exception), new Success(3)];

        $this->assertSame([[1 => $exception], [0 => 1, 2 => 3]], await(Promise\any($promises)));
    }

    public function testPendingPromiseArray()
    {
        $promises = [
            new Delayed(20, 1),
            new Delayed(30, 2),
            new Delayed(10, 3),
        ];

        $this->assertEquals([[], [1, 2, 3]], await(Promise\any($promises)));
    }

    /**
     * @depends testMixedPromisesArray
     */
    public function testArrayKeysPreserved()
    {
        $exception = new \Exception;
        $expected = [['two' => $exception], ['one' => 1, 'three' => 3]];

        $promises = [
            'one' => new Delayed(20, 1),
            'two' => new Failure($exception),
            'three' => new Delayed(10, 3),
        ];

        $this->assertEquals($expected, await(Promise\any($promises)));
    }

    public function testNonPromise()
    {
        $this->expectException(\TypeError::class);

        Promise\any([1]);
    }
}
