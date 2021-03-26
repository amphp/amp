<?php

namespace Amp\Test;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\asyncValue;
use function Amp\await;

class AllTest extends AsyncTestCase
{
    public function testEmptyArray(): void
    {
        self::assertSame([], await(Promise\all([])));
    }

    public function testSuccessfulPromisesArray(): void
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        self::assertSame([1, 2, 3], await(Promise\all($promises)));
    }

    public function testPendingPromiseArray(): void
    {
        $promises = [
            asyncValue(20, 1),
            asyncValue(30, 2),
            asyncValue(10, 3),
        ];

        self::assertSame([1, 2, 3], await(Promise\all($promises)));
    }

    public function testArrayKeysPreserved(): void
    {
        $expected = ['one' => 1, 'two' => 2, 'three' => 3];

        $promises = [
            'one' => asyncValue(20, 1),
            'two' => asyncValue(30, 2),
            'three' => asyncValue(10, 3),
        ];

        self::assertEquals($expected, await(Promise\all($promises)));
    }

    public function testNonPromise(): void
    {
        $this->expectException(\TypeError::class);

        Promise\all([1]);
    }
}
