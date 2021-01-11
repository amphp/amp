<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Promise;

class WrapTest extends BaseTest
{
    public function testSuccess(): void
    {
        $deferred = new Deferred();

        $promise = Promise\wrap($deferred->promise(), function () {
            return 2;
        });

        $deferred->resolve(1);

        $result = Promise\wait($promise);

        self::assertSame(2, $result);
    }

    public function testFailure(): void
    {
        $deferred = new Deferred();

        $promise = Promise\wrap($deferred->promise(), function () {
            throw new \Exception('bar');
        });

        $deferred->fail(new \Exception('foo'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('bar');

        Promise\wait($promise);
    }
}
