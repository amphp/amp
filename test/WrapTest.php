<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use function Amp\await;

class WrapTest extends AsyncTestCase
{
    public function testSuccess(): void
    {
        $deferred = new Deferred();

        $promise = Promise\wrap($deferred->promise(), function () {
            return 2;
        });

        $deferred->resolve(1);

        $result = await($promise);

        $this->assertSame(2, $result);
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

        await($promise);
    }
}
