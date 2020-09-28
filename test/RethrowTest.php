<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use function React\Promise\reject;
use function Amp\sleep;

class RethrowTest extends AsyncTestCase
{
    public function testRethrow(): void
    {
        $exception = new \Exception;

        $promise = new Failure($exception);

        Promise\rethrow($promise);

        $invoked = false;
        Loop::setErrorHandler(function (\Throwable $exception) use (&$invoked, &$reason): void {
            $invoked = true;
            $reason = $exception;
        });

        sleep(0); // Tick the event loop.

        $this->assertTrue($invoked);
        $this->assertSame($reason, $exception);
    }

    /**
     * @depends testRethrow
     */
    public function testReactPromise(): void
    {
        $exception = new \Exception;

        $promise = reject($exception);

        Promise\rethrow($promise);

        $invoked = false;
        Loop::setErrorHandler(function (\Throwable $exception) use (&$invoked, &$reason): void {
            $invoked = true;
            $reason = $exception;
        });

        sleep(0); // Tick the event loop.

        $this->assertTrue($invoked);
        $this->assertSame($reason, $exception);
    }
}
