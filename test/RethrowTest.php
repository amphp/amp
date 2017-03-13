<?php

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\Loop;
use PHPUnit\Framework\TestCase;
use function React\Promise\reject;

class RethrowTest extends TestCase {
    public function testRethrow() {
        $exception = new \Exception;

        try {
            Loop::run(function () use ($exception) {
                $promise = new Failure($exception);

                Amp\rethrow($promise);
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
    public function testReactPromise() {
        $exception = new \Exception;

        try {
            Loop::run(function () use ($exception) {
                $promise = reject($exception);

                Amp\rethrow($promise);
            });
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail('Failed promise reason should be thrown from loop');
    }
}
