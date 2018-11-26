<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Promise;

class WrapTest extends \PHPUnit\Framework\TestCase
{
    public function testSuccess()
    {
        $deferred = new Deferred();

        $promise = Promise\wrap($deferred->promise(), function () {
            return 2;
        });

        $deferred->resolve(1);

        $result = Promise\wait($promise);

        $this->assertSame(2, $result);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage bar
     */
    public function testFailure()
    {
        $deferred = new Deferred();

        $promise = Promise\wrap($deferred->promise(), function () {
            throw new \Exception('bar');
        });

        $deferred->fail(new \Exception('foo'));

        Promise\wait($promise);
    }
}
