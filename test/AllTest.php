<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function React\Promise\resolve;
use function Amp\await;

class AllTest extends AsyncTestCase
{
    public function testEmptyArray(): void
    {
        $this->assertSame([], await(Promise\all([])));
    }

    public function testSuccessfulPromisesArray(): void
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $this->assertSame([1, 2, 3], await(Promise\all($promises)));
    }

    public function testPendingPromiseArray(): void
    {
        $promises = [
            new Delayed(20, 1),
            new Delayed(30, 2),
            new Delayed(10, 3),
        ];

        $this->assertSame([1, 2, 3], await(Promise\all($promises)));
    }

    public function testReactPromiseArray(): void
    {
        $promises = [
            new Delayed(20, 1),
            resolve(2),
        ];

        $this->assertEquals([1, 2], await(Promise\all($promises)));
    }

    public function testArrayKeysPreserved(): void
    {
        $expected = ['one' => 1, 'two' => 2, 'three' => 3];

        $promises = [
            'one' => new Delayed(20, 1),
            'two' => new Delayed(30, 2),
            'three' => new Delayed(10, 3),
        ];

        $this->assertEquals($expected, await(Promise\all($promises)));
    }

    public function testNonPromise(): void
    {
        $this->expectException(\TypeError::class);

        Promise\all([1]);
    }
}
