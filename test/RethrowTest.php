<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use function Amp\delay;

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

        delay(0); // Tick the event loop.

        $this->assertTrue($invoked);
        $this->assertSame($reason, $exception);
    }
}
