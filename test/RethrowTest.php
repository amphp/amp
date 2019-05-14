<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use function React\Promise\reject;

class RethrowTest extends BaseTest
{
    public function testRethrow()
    {
        $exception = new \Exception;

        try {
            Loop::run(function () use ($exception) {
                $promise = new Failure($exception);

                Promise\rethrow($promise);
            });
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail('Failed promise reason should be thrown from loop');
    }

    /**
     * @depends testRethrow
     */
    public function testReactPromise()
    {
        $exception = new \Exception;

        try {
            Loop::run(function () use ($exception) {
                $promise = reject($exception);

                Promise\rethrow($promise);
            });
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail('Failed promise reason should be thrown from loop');
    }

    public function testNonPromise()
    {
        $this->expectException(\TypeError::class);
        Promise\rethrow(42);
    }
}
