<?php

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Interop\Async\Loop;

class RethrowTest extends \PHPUnit_Framework_TestCase {
    public function testWaitOnPendingAwaitable() {
        $exception = new \Exception;

        try {
            Loop::execute(function () use ($exception) {
                $awaitable = new Failure($exception);

                Amp\rethrow($awaitable);
            });
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail('Failed awaitable reason should be thrown from loop');
    }
}
